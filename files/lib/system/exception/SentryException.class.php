<?php
namespace wcf\system\exception;
use Exception;
use Sentry;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\WCF;
use function defined;
use function set_exception_handler;
use function wcf\functions\exception\logThrowable;
use function wcf\functions\exception\printThrowable;
use const SENTRY_DSN;
use const SENTRY_ENV;
use const SENTRY_INCLUDE_USER_CONTEXT;
use const SENTRY_LOG_BY_CORE;
use const WCF_VERSION;

/**
 * The Sentry exception class.
 * 
 * @author	Matthias Kittsteiner
 * @copyright	2011-2020 KittMedia
 * @license	Free <https://shop.kittmedia.com/core/licenses/#licenseFree>
 * @package	com.kittmedia.wcf.sentry
 */
class SentryException extends Exception implements IParameterizedEventListener {
	/**
	 * @var		\Exception
	 */
	private $rethrow;
	
	/**
	 * SentryException constructor.
	 */
	public function __construct() {
		// load required data
		require_once(__DIR__ . '/../api/sentry/autoload.php');
		
		if (!defined('SENTRY_DSN') || empty(SENTRY_DSN)) {
			return;
		}
		
		Sentry\init([
			'attach_stacktrace' => true,
			'dsn' => SENTRY_DSN,
			'environment' => SENTRY_ENV ?? 'development',
			'release' => WCF_VERSION
		]);
		
		// add user context
		if (SENTRY_INCLUDE_USER_CONTEXT && WCF::getUser()->getObjectID()) {
			Sentry\configureScope(function(Sentry\State\Scope $scope) {
				$scope->setUser([
					'id' => WCF::getUser()->getObjectID(),
					'email' => WCF::getUser()->email,
					'username' => WCF::getUser()->getTitle(),
					'ip_address' => WCF::getSession()->ipAddress
				]);
				$scope->setTag('page_locale', WCF::getLanguage()->getFixedLanguageCode());
			});
		}
		
		set_exception_handler([$this, 'handleException']);
	}
	
	/**
	 * Re-throw the exception.
	 */
	public function __destruct() {
		if ($this->rethrow) {
			if (SENTRY_LOG_BY_CORE) {
				logThrowable($this->rethrow);
			}
			
			printThrowable($this->rethrow);
		}
	}
	
	/**
	 * Executes this action.
	 * 
	 * @param	object		$eventObj	Object firing the event
	 * @param	string		$className	class name of $eventObj
	 * @param	string		$eventName	name of the event fired
	 * @param	array		&$parameters	given parameters
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) { }
	
	/**
	 * Handle any exception and log them into Sentry.
	 */
	public function handleException($exception) {
		// exclude certain generic exceptions
		if (
			$exception instanceof PermissionDeniedException
			|| $exception instanceof NamedUserException
		) {
			$exception->show();
			exit;
		}
		
		Sentry\captureException($exception);
		
		$this->rethrow = $exception;
	}
}

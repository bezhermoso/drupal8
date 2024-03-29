<?php

/**
 * @file
 * Contains \Drupal\Core\Session\SessionHandler.
 */

namespace Drupal\Core\Session;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\Error;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Default session handler.
 */
class SessionHandler implements \SessionHandlerInterface {

  /**
   * The session manager.
   *
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  protected $sessionManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * An array containing the sid and data from last read.
   *
   * @var array
   */
  protected $lastRead;

  /**
   * Constructs a new SessionHandler instance.
   *
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   The session manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(SessionManagerInterface $session_manager, RequestStack $request_stack, Connection $connection) {
    $this->sessionManager = $session_manager;
    $this->requestStack = $request_stack;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function open($save_path, $name) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function read($sid) {
    global $user;

    // Handle the case of first time visitors and clients that don't store
    // cookies (eg. web crawlers).
    $insecure_session_name = substr(session_name(), 1);
    $cookies = $this->requestStack->getCurrentRequest()->cookies;
    if (!$cookies->has(session_name()) && !$cookies->has($insecure_session_name)) {
      $user = new UserSession();
      return '';
    }

    // Otherwise, if the session is still active, we have a record of the
    // client's session in the database. If it's HTTPS then we are either have a
    // HTTPS session or we are about to log in so we check the sessions table
    // for an anonymous session with the non-HTTPS-only cookie. The session ID
    // that is in the user's cookie is hashed before being stored in the
    // database as a security measure. Thus, we have to hash it to match the
    // database.
    if ($this->requestStack->getCurrentRequest()->isSecure()) {
      // Try to load a session using the HTTPS-only secure session id.
      $values = $this->connection->query("SELECT u.*, s.* FROM {users} u INNER JOIN {sessions} s ON u.uid = s.uid WHERE s.ssid = :ssid", array(
        ':ssid' => Crypt::hashBase64($sid),
      ))->fetchAssoc();
      if (!$values) {
        // Fallback and try to load the anonymous non-HTTPS session. Use the
        // non-HTTPS session id as the key.
        if ($cookies->has($insecure_session_name)) {
          $values = $this->connection->query("SELECT u.*, s.* FROM {users} u INNER JOIN {sessions} s ON u.uid = s.uid WHERE s.sid = :sid AND s.uid = 0", array(
            ':sid' => Crypt::hashBase64($cookies->get($insecure_session_name)),
          ))->fetchAssoc();
        }
      }
    }
    else {
      // Try to load a session using the non-HTTPS session id.
      $values = $this->connection->query("SELECT u.*, s.* FROM {users} u INNER JOIN {sessions} s ON u.uid = s.uid WHERE s.sid = :sid", array(
        ':sid' => Crypt::hashBase64($sid),
      ))->fetchAssoc();
    }

    // We found the client's session record and they are an authenticated,
    // active user.
    if ($values && $values['uid'] > 0 && $values['status'] == 1) {
      // Add roles element to $user.
      $rids = $this->connection->query("SELECT ur.rid FROM {users_roles} ur WHERE ur.uid = :uid", array(
        ':uid' => $values['uid'],
      ))->fetchCol();
      $values['roles'] = array_merge(array(DRUPAL_AUTHENTICATED_RID), $rids);
      $user = new UserSession($values);
    }
    elseif ($values) {
      // The user is anonymous or blocked. Only preserve two fields from the
      // {sessions} table.
      $user = new UserSession(array(
        'session' => $values['session'],
        'access' => $values['access'],
      ));
    }
    else {
      // The session has expired.
      $user = new UserSession();
    }

    // Store the session that was read for comparison in self::write().
    $this->lastRead = array(
      'sid' => $sid,
      'value' => $user->session,
    );
    return $user->session;
  }

  /**
   * {@inheritdoc}
   */
  public function write($sid, $value) {
    global $user;

    // The exception handler is not active at this point, so we need to do it
    // manually.
    try {
      if (!$this->sessionManager->isEnabled()) {
        // We don't have anything to do if we are not allowed to save the
        // session.
        return TRUE;
      }
      // Check whether $_SESSION has been changed in this request.
      $is_changed = empty($this->lastRead) || $this->lastRead['sid'] != $sid || $this->lastRead['value'] !== $value;

      // For performance reasons, do not update the sessions table, unless
      // $_SESSION has changed or more than 180 has passed since the last
      // update.
      $needs_update = !$user->getLastAccessedTime() || REQUEST_TIME - $user->getLastAccessedTime() > Settings::get('session_write_interval', 180);

      if ($is_changed || $needs_update) {
        // Either ssid or sid or both will be added from $key below.
        $fields = array(
          'uid' => $user->id(),
          'hostname' => $this->requestStack->getCurrentRequest()->getClientIP(),
          'session' => $value,
          'timestamp' => REQUEST_TIME,
        );
        // Use the session ID as 'sid' and an empty string as 'ssid' by default.
        // read() does not allow empty strings so that's a safe default.
        $key = array('sid' => Crypt::hashBase64($sid), 'ssid' => '');
        // On HTTPS connections, use the session ID as both 'sid' and 'ssid'.
        if ($this->requestStack->getCurrentRequest()->isSecure()) {
          $key['ssid'] = Crypt::hashBase64($sid);
          $cookies = $this->requestStack->getCurrentRequest()->cookies;
          // The "secure pages" setting allows a site to simultaneously use both
          // secure and insecure session cookies. If enabled and both cookies
          // are presented then use both keys. The session ID from the cookie is
          // hashed before being stored in the database as a security measure.
          if (Settings::get('mixed_mode_sessions', FALSE)) {
            $insecure_session_name = substr(session_name(), 1);
            if ($cookies->has($insecure_session_name)) {
              $key['sid'] = Crypt::hashBase64($cookies->get($insecure_session_name));
            }
          }
        }
        elseif (Settings::get('mixed_mode_sessions', FALSE)) {
          unset($key['ssid']);
        }
        $this->connection->merge('sessions')
          ->keys($key)
          ->fields($fields)
          ->execute();
      }
      // Likewise, do not update access time more than once per 180 seconds.
      if ($user->isAuthenticated() && REQUEST_TIME - $user->getLastAccessedTime() > Settings::get('session_write_interval', 180)) {
        $this->connection->update('users')
          ->fields(array(
            'access' => REQUEST_TIME,
          ))
          ->condition('uid', $user->id())
          ->execute();
      }
      return TRUE;
    }
    catch (\Exception $exception) {
      require_once DRUPAL_ROOT . '/core/includes/errors.inc';
      // If we are displaying errors, then do so with no possibility of a
      // further uncaught exception being thrown.
      if (error_displayable()) {
        print '<h1>Uncaught exception thrown in session handler.</h1>';
        print '<p>' . Error::renderExceptionSafe($exception) . '</p><hr />';
      }
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function close() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function destroy($sid) {
    global $user;

    // Nothing to do if we are not allowed to change the session.
    if (!$this->sessionManager->isEnabled()) {
      return TRUE;
    }
    $is_https = $this->requestStack->getCurrentRequest()->isSecure();
    // Delete session data.
    $this->connection->delete('sessions')
      ->condition($is_https ? 'ssid' : 'sid', Crypt::hashBase64($sid))
      ->execute();

    // Reset $_SESSION and $user to prevent a new session from being started
    // in \Drupal\Core\Session\SessionManager::save().
    $_SESSION = array();
    $user = new AnonymousUserSession();

    // Unset the session cookies.
    $this->deleteCookie(session_name());
    if ($is_https) {
      $this->deleteCookie(substr(session_name(), 1), FALSE);
    }
    elseif (Settings::get('mixed_mode_sessions', FALSE)) {
      $this->deleteCookie('S' . session_name(), TRUE);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function gc($lifetime) {
    // Be sure to adjust 'php_value session.gc_maxlifetime' to a large enough
    // value. For example, if you want user sessions to stay in your database
    // for three weeks before deleting them, you need to set gc_maxlifetime
    // to '1814400'. At that value, only after a user doesn't log in after
    // three weeks (1814400 seconds) will his/her session be removed.
    $this->connection->delete('sessions')
      ->condition('timestamp', REQUEST_TIME - $lifetime, '<')
      ->execute();
    return TRUE;
  }

  /**
   * Deletes a session cookie.
   *
   * @param string $name
   *   Name of session cookie to delete.
   * @param bool $secure
   *   Force the secure value of the cookie.
   */
  protected function deleteCookie($name, $secure = NULL) {
    $cookies = $this->requestStack->getCurrentRequest()->cookies;
    if ($cookies->has($name) || (!$this->requestStack->getCurrentRequest()->isSecure() && $secure === TRUE)) {
      $params = session_get_cookie_params();
      if ($secure !== NULL) {
        $params['secure'] = $secure;
      }
      setcookie($name, '', REQUEST_TIME - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
      $cookies->remove($name);
    }
  }

}

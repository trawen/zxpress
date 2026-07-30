<?php
/**
 * Admin login form handling. Included from init.inc only.
 *
 * @param mysqli $db
 * @param Smarty $smarty
 */

/**
 * Verify admin password: only password_hash() / password_verify() (bcrypt etc.).
 */
function admin_password_verify(string $plain, string $stored_hash): bool
{
	$stored_hash = trim($stored_hash);
	if ($stored_hash === '') {
		return false;
	}
	return password_verify($plain, $stored_hash);
}

/** Admin UI: only level 1 or legacy NULL rows. */
function zxpress_user_is_admin_level(mixed $level): bool
{
	if ($level === null) {
		return true;
	}
	return (int) $level === 1;
}

function handle_login($db, $smarty): void
{
	if (!empty($_SESSION['login'])) {
		// Default: keep admin session alive at least 1 day.
		$idle_sec_raw = (int) (getenv('ADMIN_SESSION_IDLE_SECONDS') ?: '86400');
		// Enforce "at least a day" unless the operator explicitly disables idle logout
		// (e.g. ADMIN_SESSION_IDLE_SECONDS <= 0).
		$idle_sec = $idle_sec_raw > 0 ? max($idle_sec_raw, 86400) : $idle_sec_raw;
		if ($idle_sec > 0) {
			$now = time();
			$last = (int) ($_SESSION['admin_last_activity'] ?? 0);
			if ($last > 0 && ($now - $last) > $idle_sec) {
				$_SESSION = [];
				session_destroy();
				session_start();
				header('Location: /hyperjump.php?session_timeout=1', true, 303);
				exit;
			}
			$_SESSION['admin_last_activity'] = $now;
		}
	}

	if (empty($_SESSION['login'])) {
		$username = trim((string) ($_POST['username'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');
		// Prefer ASCII auth_submit=1; keep legacy login=войти for old cached HTML.
		$auth_submit = (string) ($_POST['auth_submit'] ?? '');
		$legacy_login_btn = ($_POST['login'] ?? null);
		$want_login = $auth_submit === '1' || $legacy_login_btn === 'войти';

		if (
			($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
			&& ($username !== '' || $password !== '')
			&& !$want_login
		) {
			error_log(
				'[FIX] Admin login POST ignored: auth_submit='
				. var_export($auth_submit, true)
				. ' login=' . var_export($legacy_login_btn, true),
			);
		}

		if ($want_login) {
			csrf_verify();
			// Select by username only — password + level are checked below. Filtering level in SQL
			// caused "correct password in DB" to look like a wrong password when level was not 1/NULL.
			$stmt = $db->prepare('SELECT id, username, password, `level` FROM users WHERE username = ?');
			$stmt->bind_param('s', $username);
			$stmt->execute();
			$t = $stmt->get_result()->fetch_assoc();

			if (!$t) {
				error_log('[FIX] Admin login failed: unknown username=' . $username);
				$smarty->assign('login_error', 1);
			} elseif (!admin_password_verify($password, (string) $t['password'])) {
				error_log(
					'[FIX] Admin login failed: bad password for user=' . $username . ' id=' . (int) $t['id'],
				);
				$smarty->assign('login_error', 1);
			} elseif (!zxpress_user_is_admin_level($t['level'] ?? null)) {
				error_log(
					'[FIX] Admin login failed: user=' . $username . ' id=' . (int) $t['id']
						. ' level=' . var_export($t['level'], true) . ' (need 1 or NULL)',
				);
				$smarty->assign('login_error_no_rights', 1);
			} else {
				session_regenerate_id(true);
				$_SESSION['login'] = 1;
				$_SESSION['username'] = $t['username'];
				$_SESSION['id_username'] = $t['id'];
				$_SESSION['admin_last_activity'] = time();

				error_log('[FIX] Admin login success: user=' . $t['username'] . ' id=' . (int) $t['id']);

				header('Location: /admin_articles.php');
				exit;
			}
		}
	}

	$smarty->assign('username', $_SESSION['username'] ?? '');
	$smarty->assign('csrf_token', csrf_token());
	$smarty->assign('login', $_SESSION['login'] ?? 0);
}

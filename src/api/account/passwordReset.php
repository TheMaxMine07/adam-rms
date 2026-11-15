<?php
require_once __DIR__ . '/../apiHead.php';

$resetCode = $_GET['code'] ?? $_POST['code'] ?? null;

if (!$resetCode) {
	header('Location: ' . $CONFIG['ROOTURL']);
	exit;
}

$DBLIB->where('passwordResetCodes_code', $bCMS->sanitizeString($resetCode));
$code = $DBLIB->getOne('passwordResetCodes');
if (!isset($code) or $code['passwordResetCodes_valid'] != '1') {
	header('Location: ' . $CONFIG['ROOTURL']); //If it fails we may as well just assume they have tried to click it a second time.
	exit;
}

if (strtotime($code['passwordResetCodes_timestamp']) < (time() - (60 * 60 * 48))) { //48 hours
	//Code has expired - send another
	if ($AUTH->forgotPassword($code['users_userid'])) die("Der Link ist abgelaufen. Wir haben dir einen neuen Link gesendet.");
	else die("Der Link ist abgelaufen. Bitte fordere einen neuen an oder wende dich an den Support.");
}

// On GET, show a confirmation page rather than immediately processing the reset.
// This prevents email security scanners (which pre-fetch links) from consuming the one-time code.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Content-Type: text/html; charset=utf-8');
	echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<title>Passwort zurücksetzen - ' . htmlspecialchars($CONFIG['PROJECT_NAME']) . '</title>';
	echo '<style>body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:linear-gradient(135deg,#f2f6fa,#dfeaf0);color:#243447;margin:0;padding:24px;display:flex;justify-content:center;align-items:center;min-height:100vh;box-sizing:border-box}';
	echo '.card{background:#fff;border:1px solid #cfdbe6;border-radius:16px;padding:40px;max-width:440px;width:100%;text-align:center;box-shadow:0 16px 48px rgba(31,48,70,.12)}';
	echo 'h1{font-size:24px;margin:0 0 15px}p{font-size:16px;line-height:1.55;color:#536477;margin:0 0 25px}';
	echo 'button{background:#317da0;color:#fff;border:none;padding:12px 30px;font-size:16px;border-radius:7px;cursor:pointer}';
	echo 'button:hover{background:#266985}</style></head><body>';
	echo '<div class="card"><h1>Passwort zurücksetzen</h1>';
	echo '<p>Klicke auf die Schaltfläche, um das Zurücksetzen deines Passworts für ' . htmlspecialchars($CONFIG['PROJECT_NAME']) . ' zu bestätigen.</p>';
	echo '<form method="POST"><input type="hidden" name="code" value="' . htmlspecialchars($resetCode) . '">';
	echo '<button type="submit">Passwort zurücksetzen</button></form></div></body></html>';
	exit;
}

// POST: actually process the reset
$DBLIB->where('users_userid', $code['users_userid']);
$DBLIB->update('users', ["users_password" => "RESET", "users_changepass" => 1]);

$DBLIB->where('passwordResetCodes_id', $code['passwordResetCodes_id']);
$DBLIB->update('passwordResetCodes', ["passwordResetCodes_valid" => "0", "passwordResetCodes_used" => "1"]);
notify(1, $code['users_userid'], false, "Passwort für " . $CONFIG['PROJECT_NAME'] . ' über den Link zum Zurücksetzen geändert');

$bCMS->auditLog("UPDATE", "users", "PASSWORD RESET", $code['users_userid'], $code['users_userid']);
$AUTH->generateToken($code['users_userid'], false, "Web", "web-session");
$AUTH->redirectToReturnAddress();
exit;

/**
 * @OA\Get(
 *     path="/account/passwordReset.php",
 *     summary="Password Reset Confirmation Page",
 *     description="Validates the reset code and shows an HTML confirmation page with a form that POSTs back to process the reset. This two-step flow prevents email security scanners from consuming the one-time code.",
 *     operationId="passwordResetConfirm",
 *     tags={"account"},
 *     @OA\Parameter(
 *         name="code",
 *         in="query",
 *         description="The password reset code from the email link",
 *         required=true,
 *         @OA\Schema(type="string"),
 *     ),
 *     @OA\Response(
 *         response="200",
 *         description="HTML confirmation page with a form to confirm the reset",
 *         @OA\MediaType(mediaType="text/html"),
 *     ),
 *     @OA\Response(
 *         response="302",
 *         description="Redirect to homepage if code is missing or invalid",
 *     ),
 * )
 *
 * @OA\Post(
 *     path="/account/passwordReset.php",
 *     summary="Process Password Reset",
 *     description="Processes the password reset: sets the user password to a temporary value, logs them in, and redirects to the forced password change page.",
 *     operationId="passwordReset",
 *     tags={"account"},
 *     @OA\Parameter(
 *         name="code",
 *         in="query",
 *         description="The password reset code from the email link (also accepted in the POST body)",
 *         required=true,
 *         @OA\Schema(type="string"),
 *     ),
 *     @OA\Response(
 *         response="302",
 *         description="Redirect to homepage on success (user is now logged in with a forced password change)",
 *     ),
 * )
 */

<?php
// quiz-lead.php
// Collects quiz data, emails it to you, and forwards the email to Mailchimp via the public embed URL.
// No PHPMailer needed; uses mail() + cURL like your existing mail.php.

// ------- helpers -------
function field($arr, $key) { return isset($arr[$key]) ? trim((string)$arr[$key]) : ''; }
function e($str){ return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function clean_header($v){ return str_replace(["\r","\n"], ' ', $v); }
function is_ajax() {
  if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
  if (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) return true;
  return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Invalid request method.';
  exit;
}

// Honeypot
$honeypot = field($_POST, 'honeypot');
if ($honeypot !== '') {
  if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok'=>true]); }
  else { echo 'Thanks!'; }
  exit;
}

// Inputs
$name          = field($_POST,'name');
$business      = field($_POST,'business');
$email         = field($_POST,'email');
$business_type = field($_POST,'business_type');
$other         = field($_POST,'other');
$marketing     = (isset($_POST['marketing']) && is_array($_POST['marketing'])) ? array_map('strval', $_POST['marketing']) : [];

// Basic validation
if ($name === '' || $business === '' || $email === '' || $business_type === '') {
  if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'Missing required fields']); }
  else { echo 'Please complete all required fields.'; }
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'Invalid email']); }
  else { echo 'Please enter a valid email.'; }
  exit;
}

// Build email body
$channels_list = $marketing ? implode(', ', $marketing) : 'None provided';
$lines = [
  "<strong>Name:</strong> " . e($name),
  "<strong>Email:</strong> " . e($email),
  "<strong>Business:</strong> " . e($business),
  "<strong>Business Type:</strong> " . e($business_type),
  "<strong>Marketing Tried:</strong> " . e($channels_list),
];
if ($other !== '') { $lines[] = "<strong>Other Notes:</strong> " . e($other); }

$body = "<html><body style='font-family:Arial,Helvetica,sans-serif; font-size:16px;'>"
      . "<h2 style='margin:0 0 12px;'>New Quiz Lead</h2>"
      . "<p>" . implode("<br>", $lines) . "</p>"
      . "<p style='margin-top:16px; font-size:13px; color:#555;'>Source: Marketing Quiz</p>"
      . "</body></html>";

// Email settings (adjust as needed)
$recipient = "mail@bastiontech.org";         // <-- where you want to receive quiz leads
$subject   = "New Marketing Quiz Lead";
$from      = "mail@bastiontech.org";         // domain sender for SPF/DMARC

// Sanitize headers
$clean_name  = clean_header($name);
$clean_email = clean_header($email);

// Headers
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";
$headers .= "From: ".$clean_name." <".$from.">\r\n";
$headers .= "Reply-To: ".$clean_name." <".$clean_email.">\r\n";

// Send email (set envelope sender)
@mail($recipient, $subject, $body, $headers, "-f {$from}");

// --- Mailchimp forward (embed POST) ---
$mc_url = 'https://bastiontech.us22.list-manage.com/subscribe/post?u=5635c09a3332ef2cdd2c0b697&id=4a1ef11af2';
// Keep it simple like your mail.php: only EMAIL and the exact hidden b_ field.
// You can add more merge fields later if your list has them.
$mc_data = [
  'EMAIL' => $email,
  'b_5635c09a3332ef2cdd2c0b697_4a1ef11af2' => ''
];

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL            => $mc_url,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($mc_data),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_TIMEOUT        => 8,
  CURLOPT_USERAGENT      => 'BastionTech-Quiz/1.0 (+https://bastiontech.org)'
]);
$mc_response = curl_exec($ch);
$mc_err      = curl_error($ch);
$mc_code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Log MC issues but never block user
if ($mc_err || $mc_code >= 400) {
  error_log("Mailchimp quiz post failed: code=$mc_code err=$mc_err");
}

// Respond
if (is_ajax()) {
  header('Content-Type: application/json');
  echo json_encode(['ok'=>true]);
  exit;
}

// Non-AJAX fallback (JS disabled): show a tiny thank-you and bounce home
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Thanks — BastionTech</title>
  <meta http-equiv="refresh" content="3;url=/" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0f172a] text-white min-h-screen flex items-center justify-center">
  <div class="text-center p-8 border border-white/10 rounded-2xl bg-white/5">
    <h1 class="text-2xl font-bold">Thanks! We’ve received your quiz details.</h1>
    <p class="text-white/70 mt-2">We’ll be in touch soon. Redirecting…</p>
    <p class="mt-4"><a href="/" class="underline">Go back now</a></p>
  </div>
</body>
</html>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $name          = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email         = isset($_POST['email']) ? trim($_POST['email']) : '';
    $message       = isset($_POST['message']) ? trim($_POST['message']) : '';
    $budget        = isset($_POST['budget']) ? trim($_POST['budget']) : '';
    $honeypot      = isset($_POST['honeypot']) ? trim($_POST['honeypot']) : '';

    // NEW: additional fields
    $found_us      = isset($_POST['found_us']) ? trim($_POST['found_us']) : '';
    $referrer_name = isset($_POST['referrer_name']) ? trim($_POST['referrer_name']) : '';

    // Required fields
    if ($name === '' || $email === '' || $message === '') {
        echo "All fields are required. Please fill in Name, Email, and Message.";
        exit;
    }

    // If user indicated Referral or Family/Friend, require referrer_name
    $referral_triggers = ['Referral', 'Family/Friend', 'Friend / Family']; // include possible variations
    if (in_array($found_us, $referral_triggers, true) && $referrer_name === '') {
        echo "Please provide the name of the person who referred you.";
        exit;
    }

    // Honeypot
    if ($honeypot !== '') {
        echo "Spam detected. Your submission has been rejected.";
        exit;
    }

    // Basic email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Please enter a valid email address.";
        exit;
    }

    // CAPTCHA
    $userAnswer    = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';
    $correctAnswer = isset($_POST['captcha-answer']) ? trim($_POST['captcha-answer']) : '';
    if ($userAnswer === '' || $userAnswer !== $correctAnswer) {
        error_log("CAPTCHA answer is incorrect.");
        echo "CAPTCHA answer is incorrect. Please try again.";
        exit;
    }

    /* ===== Forward to Mailchimp (simple embed POST, no API key) ===== */
    // Use the *same* action URL as your onsite embed form:
    $mc_url = 'https://bastiontech.us22.list-manage.com/subscribe/post?u=5635c09a3332ef2cdd2c0b697&id=4a1ef11af2';

    // Only collect the email (you can add FNAME later if you want)
    $mc_data = [
      'EMAIL' => $email,
      // This honeypot name must match your embed’s hidden field exactly:
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
      CURLOPT_USERAGENT      => 'BastionTech/1.0 (+https://bastiontech.org)'
    ]);
    $mc_response = curl_exec($ch);
    $mc_err      = curl_error($ch);
    $mc_code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Optional: log but DO NOT block user if MC hiccups
    if ($mc_err || $mc_code >= 400) {
      error_log("Mailchimp embed post failed: code=$mc_code err=$mc_err");
    }


    // Sanitize for headers (prevent header injection)
    $clean_name  = str_replace(["\r", "\n"], ' ', $name);
    $clean_email = str_replace(["\r", "\n"], '', $email);

    // Prepare headers (domain From; user in Reply-To)
    $from = "mail@bastiontech.org";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ".$clean_name." <".$from.">\r\n";
    $headers .= "Reply-To: ".$clean_name." <".$clean_email.">\r\n";

    // Prepare email body (HTML)
    $safe_name      = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safe_email     = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safe_budget    = htmlspecialchars($budget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safe_message   = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $safe_found_us  = htmlspecialchars($found_us, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safe_referrer  = htmlspecialchars($referrer_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $text = "
      <html><body style='font-family:Arial,Helvetica,sans-serif; font-size:16px;'>
        <h2 style='margin:0 0 12px;'>New Bastion Tech Inquiry</h2>
        <p><strong>Name:</strong> {$safe_name}</p>
        <p><strong>Email:</strong> {$safe_email}</p>"
        . ($safe_budget !== '' ? "<p><strong>Budget / Service:</strong> {$safe_budget}</p>" : "")
        . ($safe_found_us !== '' ? "<p><strong>How they found us:</strong> {$safe_found_us}</p>" : "")
        . ($safe_referrer !== '' ? "<p><strong>Referrer:</strong> {$safe_referrer}</p>" : "")
        . "<p><strong>Message:</strong><br>{$safe_message}</p>
      </body></html>
    ";

    $recipient = "mail@bastiontech.org";
    $subject   = 'New Bastion Tech Inquiry';

    // Send email (set envelope sender for SPF/DMARC)
    if (mail($recipient, $subject, $text, $headers, "-f {$from}")) {
        header('Content-Type: text/html; charset=utf-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Message sent — BastionTech</title>
  <meta http-equiv="refresh" content="3;url=/" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#48668b',
            sand: '#cfab6e',
            coal: '#0b0f14',
            ink: '#0f172a',
          },
          boxShadow: { glow: '0 10px 30px rgba(72,102,139,.3)' },
          fontFamily: {
            display: ['Inter','ui-sans-serif','system-ui','sans-serif'],
            sans: ['Inter','ui-sans-serif','system-ui','sans-serif']
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    .bg-glow::before{
      content:""; position:fixed; inset:0; z-index:-1;
      background:
        radial-gradient(60% 60% at 20% 10%, rgba(72,102,139,.35), rgba(11,15,20,0) 60%),
        radial-gradient(30rem 30rem at 85% 25%, rgba(207,171,110,.30), rgba(11,15,20,0) 60%);
      filter: blur(0.5px);
    }
  </style>
</head>
<body class="bg-ink text-white font-sans min-h-screen bg-glow">
  <main class="min-h-screen flex items-center justify-center px-6">
    <div class="w-full max-w-lg">
      <div class="relative rounded-2xl border border-white/10 bg-white/5 backdrop-blur p-8 shadow-glow">
        <div class="flex items-center justify-center mb-6">
          <img src="/img/BastionLogo.png" alt="BastionTech" class="h-10 w-auto" />
        </div>

        <div class="mx-auto w-16 h-16 rounded-full flex items-center justify-center bg-primary/20 border border-primary/40 shadow-glow">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>

        <h1 class="mt-6 text-center text-2xl font-extrabold tracking-tight">
          Thanks! Your message is on its way.
        </h1>
        <p class="mt-2 text-center text-white/70">
          We usually reply within one business day. You’ll be redirected to the homepage shortly.
        </p>

        <div class="mt-6 flex items-center justify-center gap-3 text-sm">
          <a href="/" class="inline-flex items-center gap-2 rounded-xl bg-primary hover:bg-primary/90 px-4 py-2 font-semibold">
            Go now
          </a>
          <span class="text-white/60" aria-live="polite">Redirecting in <span id="count">3</span>s…</span>
        </div>
      </div>

      <p class="mt-6 text-center text-xs text-white/50">
        Having trouble? <a href="/" class="underline hover:text-white">Return to bastiontech.org</a>
      </p>
    </div>
  </main>

  <script>
    (function() {
      var seconds = 3, el = document.getElementById('count');
      var tick = setInterval(function(){
        seconds--; if (el) el.textContent = seconds;
        if (seconds <= 0) { clearInterval(tick); window.location.href = "/"; }
      }, 1000);
    })();
  </script>
</body>
</html>
HTML;
        exit;
    } else {
        error_log("Error sending the email");
        echo "Error sending the email.";
        exit;
    }
} else {
    error_log("Invalid request method");
    echo "Invalid request method.";
    exit;
}
?>

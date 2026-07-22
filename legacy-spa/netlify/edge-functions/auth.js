const loginPageHtml = (showError) => `
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BT Bautechnik - Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-gradient-start: #0a0f1d;
      --bg-gradient-end: #070a13;
      --card-bg: rgba(255, 255, 255, 0.03);
      --card-border: rgba(255, 255, 255, 0.08);
      --brand-accent: #0056b3;
      --brand-accent-glow: rgba(0, 86, 179, 0.4);
      --text-primary: #ffffff;
      --text-secondary: #8e9bb5;
      --error-color: #ff4a4a;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, var(--bg-gradient-start), var(--bg-gradient-end));
      color: var(--text-primary);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden;
      position: relative;
    }
    
    /* Background decorative blobs */
    body::before, body::after {
      content: '';
      position: absolute;
      width: 400px;
      height: 400px;
      border-radius: 50%;
      background: radial-gradient(circle, var(--brand-accent-glow) 0%, transparent 70%);
      z-index: 0;
      pointer-events: none;
    }
    body::before {
      top: -100px;
      left: -100px;
    }
    body::after {
      bottom: -100px;
      right: -100px;
    }
    
    .login-container {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      padding: 20px;
    }
    
    .login-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 24px;
      padding: 40px 32px;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
      text-align: center;
      animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .brand-icon {
      width: 64px;
      height: 64px;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--card-border);
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 24px auto;
      color: #3b82f6;
      box-shadow: inset 0 0 12px rgba(255, 255, 255, 0.05);
    }
    
    .brand-title {
      font-family: 'Outfit', sans-serif;
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 8px;
      letter-spacing: -0.5px;
      background: linear-gradient(120deg, #ffffff, #89a7cd);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    
    .brand-subtitle {
      font-size: 14px;
      color: var(--text-secondary);
      margin-bottom: 32px;
    }
    
    .form-group {
      text-align: left;
      margin-bottom: 24px;
      position: relative;
    }
    
    .form-label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: var(--text-secondary);
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .input-wrapper {
      position: relative;
    }
    
    .form-input {
      width: 100%;
      background: rgba(0, 0, 0, 0.2);
      border: 1px solid var(--card-border);
      border-radius: 12px;
      padding: 14px 44px 14px 16px;
      font-size: 15px;
      color: #ffffff;
      font-family: inherit;
      transition: all 0.3s;
      outline: none;
    }
    
    .form-input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
      background: rgba(0, 0, 0, 0.3);
    }
    
    .btn-toggle-password {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--text-secondary);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s;
    }
    
    .btn-toggle-password:hover {
      color: #ffffff;
    }
    
    .btn-login {
      width: 100%;
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      border: none;
      border-radius: 12px;
      padding: 14px;
      font-size: 15px;
      font-weight: 600;
      color: #ffffff;
      font-family: inherit;
      cursor: pointer;
      transition: all 0.3s;
      box-shadow: 0 4px 15px rgba(29, 78, 216, 0.3);
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 8px;
    }
    
    .btn-login:hover {
      background: linear-gradient(135deg, #3b82f6, #2563eb);
      box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
      transform: translateY(-1px);
    }
    
    .btn-login:active {
      transform: translateY(1px);
    }
    
    .error-msg {
      background: rgba(255, 74, 74, 0.1);
      border: 1px solid rgba(255, 74, 74, 0.2);
      border-radius: 12px;
      padding: 12px;
      font-size: 14px;
      color: var(--error-color);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      animation: shake 0.4s;
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-6px); }
      75% { transform: translateX(6px); }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="brand-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
      </div>
      <h1 class="brand-title">BT Bautechnik</h1>
      <p class="brand-subtitle">Bitte geben Sie das Passwort ein, um auf das Rechnungstool zuzugreifen.</p>
      
      <form action="/login" method="POST">
        ${showError ? '<div class="error-msg"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>Falsches Passwort. Bitte versuchen Sie es erneut.</div>' : ''}
        
        <div class="form-group">
          <label class="form-label" for="password">Passwort</label>
          <div class="input-wrapper">
            <input class="form-input" type="password" id="password" name="password" required autofocus placeholder="••••••••">
            <button class="btn-toggle-password" type="button" onclick="togglePassword()" aria-label="Passwort anzeigen">
              <svg id="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>
        </div>
        
        <button class="btn-login" type="submit">
          Anmelden
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </button>
      </form>
    </div>
  </div>

  <script>
    function togglePassword() {
      const input = document.getElementById('password');
      const icon = document.getElementById('eye-icon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
      } else {
        input.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
      }
    }
  </script>
</body>
</html>
`;

const sha256 = async (text) => {
  const msgUint8 = new TextEncoder().encode(text);
  const hashBuffer = await crypto.subtle.digest('SHA-256', msgUint8);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  return hashHex;
};

export default async (request, context) => {
  const PASSWORD = Deno.env.get("APP_PASSWORD");

  // If no password is set in the environment variables, disable the lock completely
  if (!PASSWORD) {
    return;
  }

  const url = new URL(request.url);
  const cookies = request.headers.get("cookie") || "";
  const sessionCookie = cookies
    .split(";")
    .map(c => c.trim().split("="))
    .find(([name]) => name === "bt_auth_session");

  const expectedHash = await sha256(PASSWORD);

  // 1. Handle Logout request
  if (url.pathname === "/logout") {
    return new Response(null, {
      status: 302,
      headers: {
        "Location": "/",
        "Set-Cookie": "bt_auth_session=; Path=/; HttpOnly; Secure; SameSite=Strict; Max-Age=0"
      }
    });
  }

  // 2. Handle Login POST request
  if (request.method === "POST" && url.pathname === "/login") {
    const formData = await request.formData();
    const password = formData.get("password");
    if (password === PASSWORD) {
      return new Response(null, {
        status: 302,
        headers: {
          "Location": "/",
          "Set-Cookie": `bt_auth_session=${expectedHash}; Path=/; HttpOnly; Secure; SameSite=Strict; Max-Age=2592000` // 30 days
        }
      });
    } else {
      // Re-render login page with error
      return new Response(loginPageHtml(true), {
        status: 401,
        headers: { "Content-Type": "text/html; charset=utf-8" }
      });
    }
  }

  // 3. Check Session Cookie
  if (sessionCookie && sessionCookie[1] === expectedHash) {
    // Session is valid, allow request to proceed
    return;
  }

  // 4. If unauthorized, serve login page for HTML requests, or 401 for assets
  const accept = request.headers.get("accept") || "";
  if (accept.includes("text/html")) {
    return new Response(loginPageHtml(false), {
      status: 401,
      headers: { "Content-Type": "text/html; charset=utf-8" }
    });
  } else {
    return new Response("Unauthorized", { status: 401 });
  }
};

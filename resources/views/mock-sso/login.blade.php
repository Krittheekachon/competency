<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>Mock SSO Login</title>
  <style>
    body { font-family: sans-serif; max-width: 560px; margin: 60px auto; padding: 20px; }
    h2 { color: #1e293b; }
    .subtitle { color: #94a3b8; font-size: 13px; margin-bottom: 24px; }
    .user-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin: 10px 0; cursor: pointer; transition: 0.15s; }
    .user-card:hover { background: #eff6ff; border-color: #2563eb; }
    .name { font-weight: 700; font-size: 15px; color: #0f172a; }
    .meta { color: #64748b; font-size: 12px; margin-top: 4px; }
    .badge { display: inline-block; margin-top: 6px; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; background: #dbeafe; color: #1d4ed8; }
  </style>
</head>
<body>
  <h2>🔐 Mock SSO Login</h2>
  <p class="subtitle">Dev mode — เลือก user ที่ต้องการทดสอบ</p>

  @if($errors->any())
    <div style="color:red;margin-bottom:16px;">{{ $errors->first() }}</div>
  @endif

  @forelse($mockUsers as $user)
  <form method="POST" action="{{ route('mock.sso.login') }}">
    @csrf
    <input type="hidden" name="sso_id" value="{{ $user['id'] }}">
    <div class="user-card" onclick="this.closest('form').submit()">
      <div class="name">{{ $user['name'] }}</div>
      <div class="meta">{{ $user['workline'] }} · {{ $user['division'] }} · {{ $user['position'] }}</div>
      <span class="badge">{{ $user['role'] }}</span>
    </div>
  </form>
  @empty
    <p style="color:#94a3b8;">ยังไม่มี User ในระบบ — กรุณา Import ข้อมูลก่อน</p>
  @endforelse
</body>
</html>
@extends('layouts.app')

@section('title', 'Login')

@push('styles')
<style>
    body:has(.login-page) {
        background: var(--page-bg);
    }

    .login-page {
        min-height: 100vh;
        min-height: 100dvh;
        display: grid;
        grid-template-columns: minmax(300px, 0.9fr) minmax(380px, 1.1fr);
        background: var(--panel-bg);
    }

    .login-aside {
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
        padding: 56px clamp(36px, 5vw, 72px);
        color: var(--panel-bg);
        background-color: var(--navy);
        background-image: none;
    }

    .login-aside::after {
        content: none;
    }

    .login-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        z-index: 1;
        font-weight: 700;
    }

    .login-brand-mark {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        overflow: hidden;
        border-radius: 10px;
    }

    .login-brand-mark img { display: block; width: 40px; height: 40px; object-fit: contain; }
    .login-intro-mark img { display: block; border-radius: 6px; object-fit: contain; }
    .login-aside-copy {
        position: relative;
        z-index: 1;
        max-width: 420px;
        padding: 44px 0;
    }

    .login-aside-copy h1 {
        max-width: 420px;
        margin-bottom: 18px;
        color: var(--panel-bg);
        font-size: 38px;
        font-weight: 600;
        line-height: 1.16;
    }

    .login-aside-copy p {
        max-width: 390px;
        margin: 0;
        color: rgba(255, 255, 255, .82);
        font-size: 14px;
        line-height: 1.65;
    }

    .login-aside-foot {
        position: relative;
        z-index: 1;
        color: rgba(255, 255, 255, .72);
        font-size: 12px;
    }

    .login-main {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 48px 32px;
    }

    .login-form-wrap {
        width: min(100%, 400px);
    }

    .login-form-wrap h2 {
        margin-bottom: 8px;
        color: var(--navy);
        font-size: 26px;
        font-weight: 650;
    }

    .login-subtitle {
        margin-bottom: 30px;
        color: var(--muted);
    }

    .login-form-wrap .form-label {
        margin-bottom: 7px;
        color: var(--navy);
        font-size: 13px;
        font-weight: 600;
    }

    .login-form-wrap .form-control {
        min-height: 44px;
        border-color: var(--border);
        border-radius: 6px;
        background-color: var(--panel-bg);
        box-shadow: none;
    }

    .login-form-wrap .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(15, 118, 110, .13);
    }

    .login-password-wrap .form-control {
        padding-right: 48px;
    }

    .login-password-toggle {
        position: absolute;
        top: 50%;
        right: 8px;
        display: grid;
        width: 34px;
        height: 34px;
        transform: translateY(-50%);
        place-items: center;
        border: 0;
        border-radius: 5px;
        background: transparent;
        color: var(--navy);
    }

    .login-password-toggle:hover,
    .login-password-toggle:focus-visible {
        background: var(--page-bg);
        color: var(--primary);
    }

    .login-submit {
        min-height: 44px;
        border: 0;
        border-radius: 6px;
        background: var(--primary);
        font-weight: 600;
    }

    .login-submit:hover,
    .login-submit:focus-visible {
        background: var(--navy);
    }

    .login-submit:disabled {
        background: var(--navy);
    }

    .login-note {
        margin-top: 22px;
        color: var(--muted);
        font-size: 12px;
        line-height: 1.5;
    }

    @media (max-width: 760px) {
        .login-page {
            grid-template-columns: 1fr;
        }

        .login-aside {
            min-height: 180px;
            padding: 22px 24px;
        }

        .login-aside-copy {
            padding: 24px 0 10px;
        }

        .login-aside-copy h1 {
            font-size: 26px;
        }

        .login-aside-copy p,
        .login-aside-foot {
            display: none;
        }

        .login-main {
            align-items: flex-start;
            padding: 38px 24px 48px;
        }
    }

    .login-page {
        position: relative;
        isolation: isolate;
        display: grid;
        grid-template-columns: 1fr;
        place-items: center;
        min-height: 100vh;
        min-height: 100dvh;
        padding: clamp(18px, 4vw, 48px);
        overflow: hidden;
        background: var(--page-bg);
    }

    .login-page::before,
    .login-page::after {
        position: absolute;
        z-index: -1;
        content: '';
        pointer-events: none;
    }

    .login-page::before {
        top: -120px;
        right: -100px;
        width: min(34vw, 390px);
        height: min(34vw, 390px);
        border-radius: 62% 38% 44% 56% / 45% 48% 52% 55%;
        background: #DCEDE9;
        transform: rotate(-12deg);
    }

    .login-page::after {
        bottom: -170px;
        left: -120px;
        width: min(38vw, 440px);
        height: min(38vw, 440px);
        border-radius: 44% 56% 58% 42% / 54% 39% 61% 46%;
        background: #E7EFEE;
        transform: rotate(18deg);
    }

    .login-card {
        position: relative;
        display: grid;
        grid-template-columns: minmax(0, 1.04fr) minmax(320px, 0.96fr);
        width: min(100%, 1060px);
        min-height: 590px;
        overflow: hidden;
        border: 1px solid rgba(36, 50, 59, 0.08);
        border-radius: 22px;
        background: var(--panel-bg);
        box-shadow: 0 22px 60px rgba(36, 50, 59, 0.14);
    }

    .login-main {
        min-width: 0;
        padding: clamp(34px, 5vw, 64px);
    }

    .login-form-wrap { width: min(100%, 420px); }

    .login-form-wrap h2 {
        margin: 0 0 8px;
        color: var(--navy);
        font-size: 2rem;
        font-weight: 650;
    }

    .login-subtitle { margin-bottom: 30px; }

    .login-intro-mark {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 22px;
        color: var(--primary-dark);
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .login-intro-mark i { font-size: 1rem; }
    .login-input-wrap { position: relative; }

    .login-input-icon {
        position: absolute;
        top: 50%;
        left: 14px;
        z-index: 2;
        color: #829099;
        transform: translateY(-50%);
        pointer-events: none;
    }

    .login-input-wrap .form-control { padding-left: 42px; }
    .login-form-wrap .form-control { min-height: 46px; border-radius: 7px; background: #FFFFFF; }
    .login-password-wrap .form-control { padding-right: 48px; padding-left: 42px; }
    .login-password-toggle { right: 8px; }
    .login-options { min-height: 24px; }

    .login-options a {
        color: var(--primary-dark);
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
    }

    .login-options a:hover { text-decoration: underline; }

    .login-submit {
        min-height: 48px;
        border-radius: 7px;
        background: linear-gradient(110deg, var(--primary-dark), var(--primary));
        box-shadow: 0 5px 12px rgba(15, 118, 110, 0.18);
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .login-submit:hover,
    .login-submit:focus-visible {
        background: var(--primary-dark);
        box-shadow: 0 7px 16px rgba(15, 118, 110, 0.24);
        transform: translateY(-1px);
    }

    .login-note { padding-top: 16px; border-top: 1px solid var(--border); }

    .login-aside {
        justify-content: space-between;
        min-width: 0;
        padding: 42px 44px 38px 58px;
        background: linear-gradient(145deg, var(--navy) 0%, var(--primary-dark) 58%, var(--primary) 100%);
    }

    .login-aside::after {
        position: absolute;
        top: -12%;
        bottom: -12%;
        left: -146px;
        width: 190px;
        border-radius: 43% 57% 61% 39% / 41% 46% 54% 59%;
        background: #FFFFFF;
        content: '';
        transform: rotate(5deg);
    }

    .login-aside-copy { max-width: 340px; padding: 36px 0; }

    .login-aside-copy h1 {
        margin-bottom: 14px;
        font-size: clamp(1.8rem, 3vw, 2.4rem);
        font-weight: 650;
    }

    .login-aside-copy p { font-size: 0.92rem; }
    .login-aside-foot { font-size: 0.76rem; }
    .login-aside .login-brand-mark { border: 0; background: transparent; }

    @media (max-width: 820px) {
        .login-card { grid-template-columns: minmax(0, 1fr) minmax(280px, 0.85fr); min-height: 540px; }
        .login-main { padding: 38px 30px; }
        .login-aside { padding: 34px 26px 30px 48px; }
    }

    @media (max-width: 680px) {
        .login-page { display: block; padding: 14px; overflow: auto; }
        .login-page::before { top: -180px; right: -170px; width: 300px; height: 300px; }
        .login-page::after { bottom: -180px; left: -190px; width: 320px; height: 320px; }
        .login-card { grid-template-columns: 1fr; width: min(100%, 460px); min-height: 0; margin: 12px auto; border-radius: 16px; }
        .login-main { padding: 30px 24px 26px; }
        .login-intro-mark { margin-bottom: 16px; }
        .login-subtitle { margin-bottom: 24px; }
        .login-aside { min-height: 190px; padding: 24px; }
        .login-aside::after { top: -130px; right: -10%; bottom: auto; left: -10%; width: 120%; height: 170px; border-radius: 46% 54% 0 0 / 28% 30% 0 0; transform: none; }
        .login-aside .login-brand,
        .login-aside-foot { display: none; }
        .login-aside-copy { padding: 14px 4px 18px; }
        .login-aside-copy h1 { font-size: 1.55rem; }
        .login-aside-copy p { font-size: 0.84rem; }
    }
</style>
@endpush

@section('content')
<main class="login-page">
    <div class="login-card">
        <section class="login-main" aria-labelledby="login-title">
            <div class="login-form-wrap">
                <div class="login-intro-mark"><img src="{{ $companySettings->logo_path ? asset('storage/' . $companySettings->logo_path) : asset('images/worksphere-mark.svg') }}" alt="" width="22" height="22"> Worksphere</div>
                <h2 id="login-title">Hello!</h2>
            <p class="login-subtitle">Sign in to your account</p>

            @if ($errors->any())
                <div class="alert alert-danger py-2" role="alert" aria-live="assertive">
                    <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" id="login-form">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Work email</label>
                    <div class="login-input-wrap">
                        <i class="bi bi-envelope login-input-icon" aria-hidden="true"></i>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" maxlength="255" required autofocus @if($errors->has('email')) aria-describedby="email-error" aria-invalid="true" @endif>
                    </div>
                    @error('email')
                        <div class="invalid-feedback" id="email-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="position-relative login-password-wrap">
                        <i class="bi bi-lock login-input-icon" aria-hidden="true"></i>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="current-password" minlength="6" required @if($errors->has('password')) aria-describedby="password-error" aria-invalid="true" @endif>
                        <button class="login-password-toggle" type="button" id="toggle-password" aria-label="Show password" aria-controls="password" aria-pressed="false" title="Show password">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                        @error('password')
                            <div class="invalid-feedback" id="password-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="login-options d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
                        <label class="form-check-label small" for="remember">Remember me</label>
                    </div>
                    <span class="small text-muted">Active accounts only</span>
                </div>

                <button type="submit" class="btn btn-primary login-submit w-100" id="login-submit">
                    <span class="submit-label">Sign in</span>
                    <span class="submit-wait d-none"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Signing in</span>
                </button>
            </form>

                <p class="login-note" id="login-help">For password resets or account access, contact your company administrator. Accounts are created by an administrator.</p>
            </div>
        </section>

        <aside class="login-aside" aria-labelledby="welcome-title">
            <div class="login-brand">
                <span class="login-brand-mark" aria-hidden="true"><img src="{{ $companySettings->logo_path ? asset('storage/' . $companySettings->logo_path) : asset('images/worksphere-mark.svg') }}" alt="" width="40" height="40"></span>
                <span>Worksphere</span>
            </div>
            <div class="login-aside-copy">
                <h1 id="welcome-title">Welcome back!</h1>
                <p>Your company workspace for people, documents, and customer operations.</p>
            </div>
            <div class="login-aside-foot">Internal access for authorized staff</div>
        </aside>
    </div>
</main>
@endsection

@push('scripts')
<script>
    document.getElementById('toggle-password')?.addEventListener('click', function () {
        const password = document.getElementById('password');
        const isVisible = password.type === 'text';
        password.type = isVisible ? 'password' : 'text';
        this.setAttribute('aria-pressed', String(!isVisible));
        this.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
        this.title = isVisible ? 'Show password' : 'Hide password';
        this.querySelector('i').className = isVisible ? 'bi bi-eye' : 'bi bi-eye-slash';
    });

    document.getElementById('login-form')?.addEventListener('submit', function () {
        if (!this.reportValidity()) return;
        const button = document.getElementById('login-submit');
        button.disabled = true;
        button.querySelector('.submit-label').classList.add('d-none');
        button.querySelector('.submit-wait').classList.remove('d-none');
    });
</script>
@endpush
# Frontend Routes Required for Email Links

This document lists all the frontend routes that need to be implemented to handle email links sent from the backend.

## Environment Configuration

Add this to your `.env` file:

```env
FRONTEND_URL=https://your-frontend-domain.com
```

For local development:
```env
FRONTEND_URL=http://localhost:3000
```

---

## Required Frontend Routes

### 1. Password Reset Route

**URL Pattern:** `/reset-password?token={token}&email={email}`

**Purpose:** Allow users to reset their password using a token from the email.

**Example:**
```
https://your-frontend.com/reset-password?token=abc123&email=user@example.com
```

**Frontend Implementation:**
- Extract `token` and `email` from query parameters
- Display a password reset form
- Call backend API: `POST /api/v1/password/reset` with:
  ```json
  {
    "email": "user@example.com",
    "token": "abc123",
    "password": "newpassword",
    "password_confirmation": "newpassword"
  }
  ```

---

### 2. Email Verification Route

**URL Pattern:** `/verify-email?token={token}&email={email}`

**Purpose:** Verify user's email address after registration.

**Example:**
```
https://your-frontend.com/verify-email?token=xyz789&email=user@example.com
```

**Frontend Implementation:**
- Extract `token` and `email` from query parameters
- Call backend API: `GET /api/v1/email/verify?token={token}&email={email}`
- Display success/error message based on response
- Optionally redirect to login page on success

---

### 3. Waiting List Status Route

**URL Pattern:** `/waiting-list/status?email={email}&token={token}`

**Purpose:** Allow waiting list users to check their status and verify their email.

**Example:**
```
https://your-frontend.com/waiting-list/status?email=user@example.com&token=def456
```

**Frontend Implementation:**
- Extract `email` and `token` from query parameters
- Call backend API: `GET /api/v1/waiting-list/status?email={email}&token={token}`
- Display waiting list entry status:
  - `pending` - Payment not completed
  - `payment_completed` - Payment successful, waiting for account creation
  - `account_created` - Account has been created
  - `cancelled` - Entry cancelled

---

## Frontend Route Examples

### React Example

```jsx
// App.js or Router setup
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import ResetPassword from './pages/ResetPassword';
import VerifyEmail from './pages/VerifyEmail';
import WaitingListStatus from './pages/WaitingListStatus';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/reset-password" element={<ResetPassword />} />
        <Route path="/verify-email" element={<VerifyEmail />} />
        <Route path="/waiting-list/status" element={<WaitingListStatus />} />
      </Routes>
    </BrowserRouter>
  );
}
```

### Vue Example

```javascript
// router/index.js
import { createRouter, createWebHistory } from 'vue-router';
import ResetPassword from '../views/ResetPassword.vue';
import VerifyEmail from '../views/VerifyEmail.vue';
import WaitingListStatus from '../views/WaitingListStatus.vue';

const routes = [
  {
    path: '/reset-password',
    name: 'ResetPassword',
    component: ResetPassword
  },
  {
    path: '/verify-email',
    name: 'VerifyEmail',
    component: VerifyEmail
  },
  {
    path: '/waiting-list/status',
    name: 'WaitingListStatus',
    component: WaitingListStatus
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes
});
```

---

## API Integration Examples

### Password Reset Page

```javascript
// ResetPassword.jsx
import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import axios from 'axios';

function ResetPassword() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  const token = searchParams.get('token');
  const email = searchParams.get('email');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const response = await axios.post('/api/v1/password/reset', {
        email,
        token,
        password,
        password_confirmation: passwordConfirmation,
      });

      if (response.data.success) {
        setSuccess(true);
        setTimeout(() => navigate('/login'), 3000);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to reset password');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <h1>Reset Password</h1>
      {success ? (
        <p>Password reset successfully! Redirecting to login...</p>
      ) : (
        <form onSubmit={handleSubmit}>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="New Password"
            required
          />
          <input
            type="password"
            value={passwordConfirmation}
            onChange={(e) => setPasswordConfirmation(e.target.value)}
            placeholder="Confirm Password"
            required
          />
          {error && <p className="error">{error}</p>}
          <button type="submit" disabled={loading}>
            {loading ? 'Resetting...' : 'Reset Password'}
          </button>
        </form>
      )}
    </div>
  );
}
```

### Email Verification Page

```javascript
// VerifyEmail.jsx
import { useEffect, useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import axios from 'axios';

function VerifyEmail() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [status, setStatus] = useState('verifying');
  const [message, setMessage] = useState('');

  const token = searchParams.get('token');
  const email = searchParams.get('email');

  useEffect(() => {
    const verify = async () => {
      try {
        const response = await axios.get('/api/v1/email/verify', {
          params: { token, email }
        });

        if (response.data.success) {
          setStatus('success');
          setMessage(response.data.message || 'Email verified successfully!');
          setTimeout(() => navigate('/login'), 3000);
        }
      } catch (err) {
        setStatus('error');
        setMessage(err.response?.data?.message || 'Verification failed');
      }
    };

    if (token && email) {
      verify();
    } else {
      setStatus('error');
      setMessage('Invalid verification link');
    }
  }, [token, email, navigate]);

  return (
    <div>
      <h1>Email Verification</h1>
      {status === 'verifying' && <p>Verifying your email...</p>}
      {status === 'success' && <p className="success">{message}</p>}
      {status === 'error' && <p className="error">{message}</p>}
    </div>
  );
}
```

### Waiting List Status Page

```javascript
// WaitingListStatus.jsx
import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import axios from 'axios';

function WaitingListStatus() {
  const [searchParams] = useSearchParams();
  const [entry, setEntry] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const email = searchParams.get('email');
  const token = searchParams.get('token');

  useEffect(() => {
    const fetchStatus = async () => {
      try {
        const response = await axios.get('/api/v1/waiting-list/status', {
          params: { email, token }
        });

        if (response.data.success) {
          setEntry(response.data.data);
        }
      } catch (err) {
        setError(err.response?.data?.message || 'Failed to fetch status');
      } finally {
        setLoading(false);
      }
    };

    if (email && token) {
      fetchStatus();
    } else {
      setError('Invalid link');
      setLoading(false);
    }
  }, [email, token]);

  if (loading) return <p>Loading...</p>;
  if (error) return <p className="error">{error}</p>;
  if (!entry) return <p>Entry not found</p>;

  return (
    <div>
      <h1>Waiting List Status</h1>
      <p><strong>Email:</strong> {entry.email}</p>
      <p><strong>Status:</strong> {entry.status}</p>
      <p><strong>Plan:</strong> {entry.plan?.name}</p>
      {entry.coupon_code && (
        <p><strong>Coupon:</strong> {entry.coupon_code}</p>
      )}
      {entry.status === 'payment_completed' && (
        <p>Your payment has been confirmed. We'll notify you when your account is ready!</p>
      )}
      {entry.status === 'account_created' && (
        <p>Your account has been created! Check your email for login credentials.</p>
      )}
    </div>
  );
}
```

---

## Testing

### Local Development

1. Set `FRONTEND_URL` in `.env`:
   ```env
   FRONTEND_URL=http://localhost:3000
   ```

2. Ensure your frontend is running on the specified port

3. Test email links by:
   - Registering a new user (check email verification link)
   - Requesting password reset (check reset link)
   - Registering for waiting list (check status link)

### Production

1. Set `FRONTEND_URL` in production `.env`:
   ```env
   FRONTEND_URL=https://app.yourapp.com
   ```

2. Ensure all routes are properly configured in your frontend router

3. Test all email links in production environment

---

## Notes

- All email links will use the `FRONTEND_URL` from your backend configuration
- If `FRONTEND_URL` is not set, it will fall back to `APP_URL`
- Make sure your frontend routes match exactly (case-sensitive)
- Query parameters (`token`, `email`) are required for all routes
- Handle expired/invalid tokens gracefully in your frontend

---

## Security Considerations

1. **Token Expiration:** Tokens expire after a certain time (60 minutes for password reset)
2. **One-time Use:** Some tokens may be single-use only
3. **HTTPS:** Always use HTTPS in production for secure token transmission
4. **Error Handling:** Don't expose sensitive information in error messages
5. **Rate Limiting:** Implement rate limiting on your frontend to prevent abuse


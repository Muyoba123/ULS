ULS API Documentation
Version: 1.0
Base URL: http://localhost:8000/api
Authentication: JWT with HTTP-only Cookie Refresh Tokens

Table of Contents

Overview.............
Authentication Flow.............
Security Features.............
Endpoints.............
User Management.............
Authentication.............
Error Handling.............
CORS Configuration.............

Overview

The Unified Login Service (ULS) provides secure authentication using JWT access tokens and HTTP-only cookie-based refresh tokens. This implementation protects against XSS attacks by storing refresh tokens in secure, HTTP-only cookies that cannot be accessed by JavaScript.

Key Features
✅ JWT-based access tokens
✅ HTTP-only cookie refresh tokens (XSS protection)
✅ Automatic token rotation on refresh
✅ Secure cookie attributes (HttpOnly, SameSite, Secure)
✅ CORS support with credentials
✅ Token revocation on logout


Endpoints:

User Management

# CREATE USER :

Create a new user account.

Endpoint: POST /users

Request Headers:

Content-Type: application/json
Request Body:

json
{
  "email": "user@example.com",
  "password": "securepassword123",
  "username": "johndoe"  // Optional
}
Validation Rules:

email: Required, valid email format, unique
password: Required, minimum 8 characters
username: Optional, unique if provided
Success Response (201 Created):

json
{
  "id": "9d4e8c12-a3b5-4f1e-8d2c-1a2b3c4d5e6f",
  "email": "user@example.com",
  "username": "johndoe",
  "created_at": "2026-02-15T10:14:45.000000Z"
}
Error Response (422 Unprocessable Entity):

json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}

# CHANGE PASSWORD :

Change user password with current password verification.

Endpoint: POST /users/change-password

Request Headers:

Content-Type: application/json
Request Body:

json
{
  "user_id": "9d4e8c12-a3b5-4f1e-8d2c-1a2b3c4d5e6f",
  "current_password": "oldpassword123",
  "new_password": "newpassword456"
}
Validation Rules:

user_id: Required, valid UUID
current_password: Required
new_password: Required, minimum 8 characters
Success Response (200 OK):

json
{
  "message": "Password changed successfully"
}
Error Response (400 Bad Request):

json
{
  "error": "Current password is incorrect"
}

Authentication

# LOGIN :

Authenticate user and receive access token. Refresh token is set as HTTP-only cookie.

Endpoint: POST /auth/login

Request Headers:

Content-Type: application/json
Request Body:

json
{
  "identifier": "user@example.com",
  "password": "securepassword123"
}
Parameters:

identifier: Email or username
password: User password
Success Response (200 OK):

Body:

json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expires_in": 900
}
Set-Cookie Header:

refresh_token=<64-char-random-string>; Path=/api/auth; HttpOnly; SameSite=lax; Max-Age=2592000
Cookie Attributes:

Name: refresh_token
Path: /api/auth
HttpOnly: true (not accessible via JavaScript)
SameSite: lax (CSRF protection)
Secure: true (production only, HTTPS)
Max-Age: 2592000 seconds (30 days)
Error Response (401 Unauthorized):

json
{
  "error": "Invalid credentials"
}
Refresh Token
Obtain a new access token using the refresh token cookie. The old refresh token is revoked and a new one is issued (token rotation).

Endpoint: POST /auth/refresh

Request Headers:

Content-Type: application/json
Cookie: refresh_token=<token-value>
IMPORTANT

The refresh token is automatically sent from the cookie. Do NOT include it in the request body.

Request Body:

json
{}
Empty body or no body required

Success Response (200 OK):

Body:

json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expires_in": 900
}
Set-Cookie Header:

refresh_token=<new-64-char-random-string>; Path=/api/auth; HttpOnly; SameSite=lax; Max-Age=2592000
Error Responses:

401 - No Cookie:

json
{
  "error": "Refresh token not found"
}
401 - Invalid/Expired Token:

json
{
  "error": "Invalid or expired refresh token"
}
# LOGOUT :

Revoke the refresh token and clear the cookie.

Endpoint: POST /auth/logout

Request Headers:

Content-Type: application/json
Cookie: refresh_token=<token-value>
Request Body:

json
{}
Empty body or no body required

Success Response (200 OK):

Body:

json
{
  "message": "Logged out successfully"
}
Set-Cookie Header:

refresh_token=; Path=/api/auth; HttpOnly; Max-Age=0
Cookie is cleared by setting Max-Age to 0

Error Handling
Standard Error Response Format
json

{
  "error": "Error message description"
}

HTTP Status Codes

Code	Meaning	Usage:

200	OK	Successful request
201	Created	User created successfully
400	Bad Request	Invalid request data or business logic error
401	Unauthorized	Authentication failed or invalid token
422	Unprocessable Entity	Validation errors
500	Internal Server Error	Server-side error
Validation Error Response (422)

json

{
  "message": "The email has already been taken. (and 1 more error)",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password field must be at least 8 characters."]
  }
}

CORS Configuration

Supported Origins
Configure allowed origins in 
.env
:

env
CORS_ALLOWED_ORIGIN=http://localhost:3000
WARNING

When using credentials (cookies), Access-Control-Allow-Origin cannot be *. It must be a specific origin.

CORS Headers
Response Headers:

Access-Control-Allow-Origin: http://localhost:3000
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With
Access-Control-Allow-Credentials: true
Preflight Requests
The API handles OPTIONS preflight requests automatically:

Request:

OPTIONS /auth/login
Origin: http://localhost:3000
Access-Control-Request-Method: POST
Access-Control-Request-Headers: Content-Type
Response:

200 OK
Access-Control-Allow-Origin: http://localhost:3000
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With
Access-Control-Allow-Credentials: true
Access-Control-Max-Age: 86400
Configuration
Environment Variables
env
# JWT Configuration
JWT_SECRET=your_secret_key_change_me_in_production
JWT_ALGO=HS256
JWT_ACCESS_TTL=900          # 15 minutes
JWT_REFRESH_TTL=2592000     # 30 days
# Cookie Configuration
COOKIE_SECURE=false         # Set to true in production (HTTPS)
COOKIE_SAME_SITE=lax        # lax, strict, or none
COOKIE_DOMAIN=null          # Set to your domain in production
# CORS Configuration
CORS_ALLOWED_ORIGIN=http://localhost:3000
Production Recommendations
CAUTION

Before deploying to production:

Change JWT_SECRET to a strong, random value
Set COOKIE_SECURE=true (requires HTTPS)
Update CORS_ALLOWED_ORIGIN to your production domain
Set COOKIE_DOMAIN to your production domain
Consider COOKIE_SAME_SITE=strict for enhanced security
Use environment-specific .env files
Client Integration Examples
JavaScript (Fetch API)
javascript
// Login
const login = async (email, password) => {
  const response = await fetch('http://localhost:8000/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include', // Important: Send cookies
    body: JSON.stringify({ identifier: email, password })
  });
  
  const data = await response.json();
  // Store access token (e.g., in memory or localStorage)
  localStorage.setItem('access_token', data.access_token);
  return data;
};
// Refresh
const refresh = async () => {
  const response = await fetch('http://localhost:8000/api/auth/refresh', {
    method: 'POST',
    credentials: 'include' // Cookie sent automatically
  });
  
  const data = await response.json();
  localStorage.setItem('access_token', data.access_token);
  return data;
};
// Logout
const logout = async () => {
  await fetch('http://localhost:8000/api/auth/logout', {
    method: 'POST',
    credentials: 'include'
  });
  
  localStorage.removeItem('access_token');
};

Axios
javascript
import axios from 'axios';
const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  withCredentials: true // Important: Send cookies
});

// Login
const login = async (email, password) => {
  const { data } = await api.post('/auth/login', {
    identifier: email,
    password
  });
  return data;
};

// Refresh
const refresh = async () => {
  const { data } = await api.post('/auth/refresh');
  return data;
};

// Logout
const logout = async () => {
  await api.post('/auth/logout');
};

TIP

Always set credentials: 'include' (Fetch) or withCredentials: true (Axios) to send cookies with requests.

Testing
Using cURL
bash
# Create User
curl -X POST http://localhost:8000/api/users \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
# Login (save cookies)
curl -c cookies.txt -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"identifier":"test@example.com","password":"password123"}'
# Refresh (use cookies)
curl -b cookies.txt -c cookies.txt -X POST http://localhost:8000/api/auth/refresh
# Logout
curl -b cookies.txt -X POST http://localhost:8000/api/auth/logout
Using Postman
Enable Cookie Handling: Cookies are automatically managed per domain
Send Requests: Use the same tab to maintain cookie session
View Cookies: Click "Cookies" link below the Send button
Verify HttpOnly: Check that the refresh_token cookie has HttpOnly flag
For detailed Postman testing instructions, see the 
Postman Testing Guide
.

FAQ

Why is the refresh token not in the response body?

For security. HTTP-only cookies cannot be accessed by JavaScript, protecting against XSS attacks. Even if an attacker injects malicious JavaScript, they cannot steal the refresh token.

How do I send the refresh token?

You don't! The browser automatically sends the cookie with requests to /api/auth/* endpoints. Just ensure your client includes credentials (credentials: 'include' in Fetch or withCredentials: true in Axios).

What happens when the access token expires?

Call the /auth/refresh endpoint to get a new access token. The refresh token cookie is sent automatically.

Can I use this API from a different domain?
Yes, but you must:

Set CORS_ALLOWED_ORIGIN to your frontend domain
Use credentials: 'include' in your requests
Ensure cookies are enabled in the browser
How do I invalidate all sessions for a user?
Currently, logout only revokes the specific refresh token. To revoke all tokens for a user, you would need to implement a "logout all devices" feature that revokes all refresh tokens for that user ID.

Support
For issues or questions, please contact the development team or refer to the 
Implementation Plan
.

Last Updated: 2026-02-15
API Version: 1.0
<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Flipzy API",
    description: "Flipzy Property Management Platform API - Complete API documentation for property listings, messaging, analytics, and admin features.

## Authentication

This API uses Laravel Passport for authentication. To authenticate:

1. Use the `/register` or `/login` endpoint to get an access token
2. Copy the `access_token` from the response
3. Click the 'Authorize' button at the top of this page
4. Enter: `Bearer YOUR_ACCESS_TOKEN` (replace YOUR_ACCESS_TOKEN with your actual token)
5. Click 'Authorize' and then 'Close'

All protected endpoints will now use this token automatically."
)]
#[OA\Server(
    url: "/api/v1",
    description: "API v1"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Enter your access token in the format: Bearer YOUR_ACCESS_TOKEN

Get your token by:
- Registering a new account via POST /register
- Logging in via POST /login

The token will be returned in the response as 'access_token'."
)]
class SwaggerController extends Controller
{
    //
}


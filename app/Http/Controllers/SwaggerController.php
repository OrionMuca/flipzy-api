<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Flipzy API",
    description: "Flipzy Property Management Platform API - Complete API documentation for property listings, messaging, analytics, and admin features."
)]
#[OA\Server(
    url: "/api/v1",
    description: "API v1"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    name: "Authorization",
    in: "header",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
class SwaggerController extends Controller
{
    //
}


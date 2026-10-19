# Specification: Mobile API Core

## Purpose
To implement foundational API integration capabilities in the Flutter mobile application, replacing the mock architecture with real connections to the Laravel backend via Sanctum authentication and persistent secure storage.

## Requirements

### Requirement: Secure Authentication Storage
The mobile application MUST securely store authentication tokens (e.g., Sanctum token) using platform-specific encrypted storage (e.g., `flutter_secure_storage`).

#### Scenario: Token persistence after login
- GIVEN a user successfully authenticates via the login screen
- WHEN the backend returns an authentication token
- THEN the app SHALL store the token in secure storage
- AND subsequent app launches SHALL retrieve this token to maintain the authenticated session.

### Requirement: Centralized Network Client
The mobile application MUST utilize a centralized network client (e.g., Dio) for all API requests to the Laravel backend.

#### Scenario: Authenticated request interception
- GIVEN the app has a valid authentication token in secure storage
- WHEN a network request is made to an authenticated backend endpoint
- THEN the network client SHALL intercept the request and inject the authorization header (Bearer token)
- AND the request SHALL proceed to the backend successfully.

### Requirement: User Authentication Interface
The mobile application MUST provide a login interface that authenticates users against the backend Sanctum endpoint.

#### Scenario: Successful login attempt
- GIVEN the user is on the login screen
- WHEN the user enters valid credentials and submits the form
- THEN the app SHALL authenticate against the backend
- AND the app SHALL transition to the main dashboard upon success.

# Security policy

## Reporting a vulnerability

Report vulnerabilities through GitHub private vulnerability reporting: open the repository's **Security** tab and choose **Report a vulnerability**. Please do not open a public issue or pull request for a vulnerability.

Include the affected version or commit, the route or file involved, and the steps to reproduce.

## Supported versions

Only the latest release receives security fixes.

## Scope

Of particular interest: the setup token and installer, the administrator control panel and its two-factor sign-in, gold purchases (Stripe and PayPal), session and login handling, and the generated state volume that holds credentials. Findings that need a hostile operator or an already compromised host are out of scope.

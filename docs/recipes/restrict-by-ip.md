# Restrict by IP

Admit only requests from known addresses, as a check independent of who is signed in.

## Set the allowlist

```php
// config/laranail/artisan-ui.php
'allowed_ips' => [
    '10.0.0.0/8',        // office VPN
    '203.0.113.7',       // a single address
    '2001:db8::/32',     // IPv6 works too
],
```

Any other address gets `403`, logged with the reason `ip`. A malformed entry denies everyone rather than opening the panel. Behind a load balancer, configure Laravel's trusted proxies first, or every request arrives from the proxy's address. See [Authorization](../tools/authorization.md#panel-access).

---

[← Docs index](../../README.md#documentation)

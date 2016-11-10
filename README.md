# cloudflarednsupdate
Dynamically update Cloudflare DNS entry

Use Cloudflare for easy, reliable and free Dynamic DNS hosting.

This php script is designed to be run from a temrinal or from cron and will periodically ping an external host to verify IP, then compare this will the current record for the FQDN being monitored. If a change is detected it will call the Cloudflare API and update the address, sending an optional email (requires PHPMailer).

You will need to obtain your Cloudflare API key, your zone ID and your record ID to use this script.

The script is pretty much fully configurable and all DNS record options can be updated as part of the address update.

Hope it is as useful for you as it was for me.

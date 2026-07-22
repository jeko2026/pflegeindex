# Owner Legal and Hosting Questions

Answer these questions from contracts, invoices or written provider
confirmation before the final Datenschutzerklärung is approved. Do not send
passwords, keys, tokens or complete `.env` contents.

1. What is the hosting provider's exact legal company name and postal address?
2. In which country, and if known which region, is the production server
   physically located?
3. Is an Article 28 data processing agreement (AV-Vertrag) available and
   signed? Where is the private copy stored?
4. Which subprocessors does the hosting provider currently use?
5. Which fields are stored in web-server access and error logs, including IP
   address, requested URL, query string, referrer and user-agent?
6. How long are access and error logs retained, and are they also retained in
   provider backups?
7. What is the exact mailbox provider for `info@pflegeindex.com`, and where are
   its mail servers located?
8. Is mail forwarded to another provider, mailbox, device or person?
9. How long are inbox, sent, trash, spam, mail logs and mail backups retained?
10. Where are production SQLite backups stored, how are they protected, and
    what retention schedule is actually active?
11. When was the last successful restore test of a production-compatible
    backup?
12. Does the hosting account or control panel inject analytics, tracking,
    cookies, CDN, reverse-proxy, WAF or monitoring services into the site?
13. Who receives alerts for downtime, TLS expiry, low disk space, failed
    backups and application/server errors?

Record the answers and evidence date privately, then update
`docs/LEGAL_HOSTING_FACTS_CHECKLIST.md`. Do not copy an unconfirmed answer into
the public privacy page.

/* Public configuration for the static (GitHub Pages) build of the archive.
 *
 * These two values are the Supabase project URL and the publishable anon key.
 * They are designed to be public: row level security on the database allows an
 * anonymous visitor to read published records and categories only, and to insert
 * a right-of-reply submission. Nothing else is reachable with this key.
 *
 * The service role key is NOT here and must never be added here.
 */
window.PRA_CONFIG = {
  supabaseUrl: "https://zrtzdrksytfjlznlcaqs.supabase.co",
  anonKey: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InpydHpkcmtzeXRmamx6bmxjYXFzIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTMyNzAwOTEsImV4cCI6MjEwODg0NjA5MX0.5Hp1V56_Q5HLKPSmG5wNSaYAyOdQkBcytzRqzL0oR3s",
  siteName: "Public Records Archive",
  siteTagline: "A sourced archive of public documents",
  perPage: 10,
  contactEmail: ""
};

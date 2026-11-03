/* Supabase Edge Function: pdf
 *
 * Serves the PDF for a published record through a short lived signed URL, so the
 * storage bucket can stay private while the static site on GitHub Pages still has
 * a working document link.
 *
 *   GET /functions/v1/pdf?slug=<record-slug>
 *     -> 302 to a signed URL for the attached PDF (or to the external link)
 *     -> 404 if the record is not published, or has no document
 *
 * Deploy with verify_jwt = false: anonymous readers must be able to open it.
 * SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY are injected by the platform.
 */

const SUPABASE_URL = Deno.env.get("SUPABASE_URL") || "";
const SERVICE_KEY = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") || "";
const BUCKET = "pra-documents";

function text(status, message) {
  return new Response(message, {
    status: status,
    headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store" },
  });
}

Deno.serve(async (req) => {
  if (req.method !== "GET" && req.method !== "HEAD") {
    return text(405, "Method not allowed");
  }
  if (!SUPABASE_URL || !SERVICE_KEY) {
    return text(500, "This function is not configured");
  }

  const slug = (new URL(req.url).searchParams.get("slug") || "").trim();
  if (!slug) {
    return text(400, "Missing slug");
  }

  const headers = {
    apikey: SERVICE_KEY,
    Authorization: "Bearer " + SERVICE_KEY,
    Accept: "application/json",
  };

  const lookup =
    SUPABASE_URL +
    "/rest/v1/pra_documents?select=file_path,external_url&status=eq.published&limit=1&slug=eq." +
    encodeURIComponent(slug);

  let rows;
  try {
    const docRes = await fetch(lookup, { headers: headers });
    if (!docRes.ok) {
      return text(502, "Lookup failed");
    }
    rows = await docRes.json();
  } catch (err) {
    return text(502, "Lookup failed");
  }

  if (!Array.isArray(rows) || rows.length === 0) {
    return text(404, "Record not found");
  }

  const row = rows[0];

  if (!row.file_path) {
    if (row.external_url) {
      return Response.redirect(row.external_url, 302);
    }
    return text(404, "No document attached to this record");
  }

  const signHeaders = Object.assign({}, headers, { "Content-Type": "application/json" });
  const signUrl = SUPABASE_URL + "/storage/v1/object/sign/" + BUCKET + "/" + row.file_path;

  let signedURL = "";
  try {
    const signRes = await fetch(signUrl, {
      method: "POST",
      headers: signHeaders,
      body: JSON.stringify({ expiresIn: 600 }),
    });
    if (!signRes.ok) {
      return text(502, "Could not sign the file");
    }
    const signed = await signRes.json();
    signedURL = (signed && signed.signedURL) || "";
  } catch (err) {
    return text(502, "Could not sign the file");
  }

  if (!signedURL) {
    return text(502, "Could not sign the file");
  }

  return new Response(null, {
    status: 302,
    headers: { Location: SUPABASE_URL + "/storage/v1" + signedURL, "Cache-Control": "no-store" },
  });
});

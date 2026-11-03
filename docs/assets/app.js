/* Shared helpers for the static archive. No framework, no build step. */

const CFG = window.PRA_CONFIG || {};
const REST = (CFG.supabaseUrl || "") + "/rest/v1";
const FUNCTIONS = (CFG.supabaseUrl || "") + "/functions/v1";

function sbHeaders(extra) {
  const headers = {
    apikey: CFG.anonKey || "",
    Authorization: "Bearer " + (CFG.anonKey || ""),
    Accept: "application/json"
  };
  return Object.assign(headers, extra || {});
}

async function sbGet(path, params, extraHeaders) {
  const url = REST + path + (params ? "?" + params : "");
  const res = await fetch(url, { headers: sbHeaders(extraHeaders) });
  if (!res.ok) {
    throw new Error("Request failed with status " + res.status);
  }
  const data = await res.json();
  return { data: data, range: res.headers.get("content-range") };
}

function esc(value) {
  const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
  return String(value === null || value === undefined ? "" : value).replace(/[&<>"']/g, function (c) {
    return map[c];
  });
}

function fmtDate(value) {
  if (!value) return "";
  const d = new Date(value.length === 10 ? value + "T00:00:00Z" : value);
  if (isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
}

const SOURCE_LABELS = {
  court_order: "Court order or judgment",
  cag_report: "CAG report",
  parliament_record: "Parliament record",
  government_notification: "Government notification",
  official_report: "Official report",
  news_report: "News report",
  filing: "Public filing",
  other: "Other public document"
};

function sourceLabel(type) {
  return SOURCE_LABELS[type] || "Public document";
}

function enc(value) {
  return encodeURIComponent(String(value === null || value === undefined ? "" : value));
}

function param(name) {
  return new URLSearchParams(window.location.search).get(name) || "";
}

function pdfUrl(slug) {
  return FUNCTIONS + "/pdf?slug=" + enc(slug);
}

function showError(target, message) {
  target.innerHTML = '<div class="alert alert-error">' + esc(message) + "</div>";
}

function plural(n, one, many) {
  return n + " " + (n === 1 ? one : many);
}

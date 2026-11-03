(function () {
  const root = document.getElementById("record");
  const slug = param("slug");

  if (!slug) {
    root.innerHTML = '<div class="empty"><h1>No record requested</h1><p><a href="index.html">Back to the archive</a></p></div>';
    return;
  }

  function render(doc, category, replies) {
    const chips = [];
    if (category) {
      chips.push('<a class="chip" href="index.html?category=' + enc(category.slug) + '">' + esc(category.name) + "</a>");
    }
    chips.push('<span class="chip chip-quiet">' + esc(sourceLabel(doc.source_type)) + "</span>");
    if (doc.doc_date) {
      chips.push("<time>Document dated " + esc(fmtDate(doc.doc_date)) + "</time>");
    }
    if (doc.published_at) {
      chips.push('<span class="muted">Added ' + esc(fmtDate(doc.published_at)) + "</span>");
    }

    const sourceRows = [];
    sourceRows.push("<dt>Source type</dt><dd>" + esc(sourceLabel(doc.source_type)) + "</dd>");
    sourceRows.push("<dt>Document title</dt><dd>" + esc(doc.source_title) + "</dd>");
    if (doc.source_publisher) {
      sourceRows.push("<dt>Issued by</dt><dd>" + esc(doc.source_publisher) + "</dd>");
    }
    if (doc.source_date) {
      sourceRows.push("<dt>Source date</dt><dd>" + esc(fmtDate(doc.source_date)) + "</dd>");
    }
    if (doc.source_url) {
      sourceRows.push(
        '<dt>Original link</dt><dd><a href="' + esc(doc.source_url) + '" target="_blank" rel="noopener nofollow">' +
          esc(doc.source_url) + "</a></dd>"
      );
    }

    let fileBlock = "";
    if (doc.file_path) {
      const link = pdfUrl(doc.slug);
      fileBlock =
        '<section class="panel"><h2>Document</h2>' +
        '<p class="file-line"><a class="button" href="' + esc(link) + '" target="_blank" rel="noopener">Open the PDF</a>' +
        '<span class="muted small">served through a short lived signed link</span></p>' +
        '<div class="viewer"><iframe src="' + esc(link) + '" title="PDF viewer" loading="lazy"></iframe></div>' +
        '<p class="muted small">The preview may not load in some browsers. Use the button above to open the file.</p>' +
        "</section>";
    } else if (doc.external_url) {
      fileBlock =
        '<section class="panel"><h2>Document</h2>' +
        '<p class="file-line"><a class="button" href="' + esc(doc.external_url) + '" target="_blank" rel="noopener">Open the document</a>' +
        '<span class="muted small">hosted at the original source</span></p></section>';
    }

    const replyBlock = replies.length
      ? '<div class="replies">' +
        replies
          .map(function (r) {
            const who = esc(r.name) + (r.organisation ? ", " + esc(r.organisation) : "");
            return (
              "<blockquote><p>" + esc(r.message) + "</p><footer>" + who +
              ' <span class="muted">&middot; ' + esc(fmtDate(r.created_at)) + "</span></footer></blockquote>"
            );
          })
          .join("") +
        "</div>"
      : "";

    root.innerHTML =
      '<p class="crumb"><a href="index.html">Archive</a> / ' +
      esc(category ? category.name : "Record") + "</p>" +
      '<header class="record-head"><h1>' + esc(doc.title) + "</h1>" +
      '<p class="record-meta">' + chips.join("") + "</p></header>" +
      '<section class="panel"><h2>What this record is</h2>' +
      '<p class="summary">' + esc(doc.summary) + "</p>" +
      (doc.body ? '<div class="body-text">' + esc(doc.body) + "</div>" : "") +
      "</section>" +
      '<section class="panel panel-source"><h2>Source</h2>' +
      '<dl class="source-grid">' + sourceRows.join("") + "</dl>" +
      '<p class="verify-note">Please read the original document before drawing any conclusion. This page only indexes it.</p>' +
      "</section>" +
      fileBlock +
      '<section class="panel"><h2>Right of reply</h2>' +
      "<p>If you are named in this record, or you represent an organisation that is, you can send a reply, a correction or a clarification. Replies that stay on the document are published on this page.</p>" +
      '<p><a class="button button-quiet" href="reply.html?document=' + enc(doc.slug) + '">Send a reply</a></p>' +
      replyBlock +
      "</section>" +
      '<p class="disclaimer">This archive republishes public documents with attribution. It does not assert that any person is guilty of an offence. A criminal allegation is established only by a court. Read the <a href="about.html">editorial policy</a>.</p>';
  }

  async function load() {
    let doc = null;
    try {
      const res = await sbGet("/pra_documents", "select=*&slug=eq." + enc(slug) + "&status=eq.published&limit=1");
      doc = (res.data || [])[0] || null;
    } catch (e) {
      showError(root, "The archive could not be loaded right now. Please try again in a moment.");
      return;
    }

    if (!doc) {
      root.innerHTML =
        '<div class="empty"><h1>Record not found</h1><p>This record is not published, or the link is wrong.</p>' +
        '<p><a href="index.html">Back to the archive</a></p></div>';
      return;
    }

    document.title = doc.title + " \u00b7 Gandhi Files";

    let category = null;
    if (doc.category_id) {
      try {
        const res = await sbGet("/pra_categories", "select=name,slug&id=eq." + enc(doc.category_id) + "&limit=1");
        category = (res.data || [])[0] || null;
      } catch (e) {
        category = null;
      }
    }

    let replies = [];
    try {
      const res = await sbGet(
        "/pra_published_replies",
        "select=name,organisation,message,created_at&document_id=eq." + enc(doc.id) + "&order=created_at.asc"
      );
      replies = res.data || [];
    } catch (e) {
      replies = [];
    }

    render(doc, category, replies);
  }

  load();
})();

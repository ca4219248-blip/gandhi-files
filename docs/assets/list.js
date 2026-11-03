(function () {
  const listEl = document.getElementById("records");
  const countEl = document.getElementById("count");
  const pagerEl = document.getElementById("pager");
  const form = document.getElementById("filters");
  const qInput = form.querySelector('input[name="q"]');
  const catSelect = form.querySelector('select[name="category"]');

  const q = param("q");
  const cat = param("category");
  const page = Math.max(1, parseInt(param("page") || "1", 10) || 1);
  const per = CFG.perPage || 10;

  qInput.value = q;

  function buildUrl(overrides) {
    const params = new URLSearchParams();
    const values = { q: q, category: cat, page: "" };
    Object.keys(overrides || {}).forEach(function (k) {
      values[k] = overrides[k];
    });
    Object.keys(values).forEach(function (k) {
      if (values[k]) params.set(k, values[k]);
    });
    const s = params.toString();
    return "index.html" + (s ? "?" + s : "");
  }

  function renderRows(rows) {
    if (!rows.length) {
      listEl.innerHTML =
        '<li class="empty"><p>Nothing matches this search yet.</p>' +
        "<p>The archive only lists records that carry a source. Try a broader term.</p></li>";
      return;
    }
    listEl.innerHTML = rows
      .map(function (doc) {
        const catName = doc.pra_categories && doc.pra_categories.name ? doc.pra_categories.name : "";
        const catSlug = doc.pra_categories && doc.pra_categories.slug ? doc.pra_categories.slug : "";
        const hasFile = !!doc.file_path || !!doc.external_url;
        const chips = [];
        if (catName) {
          chips.push('<a class="chip" href="' + buildUrl({ category: catSlug, page: "" }) + '">' + esc(catName) + "</a>");
        }
        chips.push('<span class="chip chip-quiet">' + esc(sourceLabel(doc.source_type)) + "</span>");
        if (doc.doc_date) {
          chips.push("<time>" + esc(fmtDate(doc.doc_date)) + "</time>");
        }
        return (
          '<li class="record">' +
          '<div class="record-meta">' + chips.join("") + "</div>" +
          '<h2><a href="record.html?slug=' + enc(doc.slug) + '">' + esc(doc.title) + "</a></h2>" +
          '<p class="record-summary">' + esc(shorten(doc.summary, 220)) + "</p>" +
          '<p class="record-source"><span>Source:</span> ' + esc(doc.source_title) +
          (hasFile ? ' <span class="ok">&middot; document attached</span>' : "") +
          "</p></li>"
        );
      })
      .join("");
  }

  function shorten(text, length) {
    const s = String(text || "").replace(/\s+/g, " ").trim();
    return s.length <= length ? s : s.slice(0, length).replace(/\s+\S*$/, "") + "...";
  }

  function renderPager(total) {
    const pages = Math.max(1, Math.ceil(total / per));
    if (pages <= 1) {
      pagerEl.innerHTML = "";
      return;
    }
    const parts = [];
    if (page > 1) {
      parts.push('<a href="' + buildUrl({ page: String(page - 1) }) + '">&larr; Newer</a>');
    }
    parts.push("<span>Page " + page + " of " + pages + "</span>");
    if (page < pages) {
      parts.push('<a href="' + buildUrl({ page: String(page + 1) }) + '">Older &rarr;</a>');
    }
    pagerEl.innerHTML = parts.join("");
  }

  async function loadCategories() {
    try {
      const res = await sbGet("/pra_categories", "select=id,name,slug&order=name.asc");
      const cats = res.data || [];
      cats.forEach(function (c) {
        const opt = document.createElement("option");
        opt.value = c.slug;
        opt.textContent = c.name;
        if (c.slug === cat) opt.selected = true;
        catSelect.appendChild(opt);
      });
      return cats;
    } catch (e) {
      return [];
    }
  }

  async function load() {
    const cats = await loadCategories();
    const filters = ["status=eq.published"];
    const match = cats.find(function (c) {
      return c.slug === cat;
    });
    if (cat && match) filters.push("category_id=eq." + enc(match.id));
    if (q) {
      const needle = enc("*" + q.replace(/[*,]/g, " ") + "*");
      filters.push(
        "or=(title.ilike." + needle + ",summary.ilike." + needle + ",source_title.ilike." + needle + ")"
      );
    }

    const select =
      "select=id,title,slug,summary,doc_date,published_at,source_type,source_title,file_path,external_url,pra_categories(name,slug)";
    const query =
      select + "&" + filters.join("&") + "&order=published_at.desc.nullslast&limit=" + per + "&offset=" + (page - 1) * per;

    let res;
    try {
      res = await sbGet("/pra_documents", query, { Prefer: "count=exact" });
    } catch (e) {
      countEl.textContent = "";
      showError(listEl, "The archive could not be loaded right now. Please try again in a moment.");
      return;
    }

    const rows = res.data || [];
    let total = rows.length;
    if (res.range) {
      const bits = res.range.split("/");
      if (bits.length === 2 && bits[1] !== "*") {
        const parsed = parseInt(bits[1], 10);
        if (!isNaN(parsed)) total = parsed;
      }
    }

    countEl.textContent = plural(total, "record", "records") + (q ? ' matching "' + q + '"' : "");
    renderRows(rows);
    renderPager(total);
  }

  load();
})();

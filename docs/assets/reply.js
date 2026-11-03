(function () {
  const form = document.getElementById("reply-form");
  const notice = document.getElementById("notice");
  const docSelect = document.getElementById("document_id");
  const submitBtn = document.getElementById("submit");
  const wantedSlug = param("document");

  async function fillDocuments() {
    try {
      const res = await sbGet("/pra_documents", "select=id,title,slug&status=eq.published&order=published_at.desc&limit=100");
      const rows = res.data || [];
      rows.forEach(function (row) {
        const opt = document.createElement("option");
        opt.value = row.id;
        opt.textContent = row.title;
        opt.dataset.slug = row.slug;
        if (wantedSlug && row.slug === wantedSlug) opt.selected = true;
        docSelect.appendChild(opt);
      });
    } catch (e) {
      /* the form still works without the record list */
    }
  }

  function fail(message) {
    notice.innerHTML = '<div class="alert alert-error">' + esc(message) + "</div>";
    notice.scrollIntoView({ block: "center" });
  }

  form.addEventListener("submit", async function (event) {
    event.preventDefault();

    if (form.website.value !== "") {
      return;
    }

    const name = form.name.value.trim();
    const email = form.email.value.trim();
    const organisation = form.organisation.value.trim();
    const message = form.message.value.trim();
    const documentId = form.document_id.value;

    if (name.length < 2) return fail("Please enter your name.");
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return fail("Please enter a valid email address.");
    if (message.length < 20) return fail("Please write at least 20 characters so we can act on your reply.");
    if (message.length > 5000) return fail("Please keep the reply under 5000 characters.");

    const payload = {
      name: name,
      email: email,
      organisation: organisation || null,
      message: message,
      status: "received"
    };
    if (documentId) payload.document_id = documentId;

    submitBtn.disabled = true;
    submitBtn.textContent = "Sending\u2026";

    try {
      const res = await fetch(REST + "/pra_reply_requests", {
        method: "POST",
        headers: sbHeaders({ "Content-Type": "application/json", Prefer: "return=minimal" }),
        body: JSON.stringify(payload)
      });
      if (!res.ok) {
        throw new Error("status " + res.status);
      }
      form.style.display = "none";
      notice.innerHTML =
        '<div class="alert alert-success">Thank you. Your reply has been received and will be reviewed. ' +
        "We will use the email address you gave us if we need to check anything.</div>";
      notice.scrollIntoView({ block: "center" });
    } catch (e) {
      submitBtn.disabled = false;
      submitBtn.textContent = "Send reply";
      fail("The reply could not be saved. Please try again in a moment.");
    }
  });

  fillDocuments();
})();

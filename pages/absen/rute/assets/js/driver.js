// gg_app/pages/rute/assets/js/driver.js
(() => {
  "use strict";

  const SERVER     = window.__SERVER || {};
  const API_URL    = (typeof window.API_URL === "string" && window.API_URL) ? window.API_URL : "api.php";
  const LOGIN_USER = (typeof window.LOGIN_USER === "string" && window.LOGIN_USER.trim()) ? window.LOGIN_USER.trim() : "";

  // ======== State dari SSR ========
  const db = {
    driver: {
      name   : (SERVER.driver && SERVER.driver.name) || LOGIN_USER,
      vehicle: (SERVER.driver && SERVER.driver.vehicle) || "",
      batchId: (SERVER.driver && (SERVER.driver.batchId ?? null)) ?? null,
    },
    vehicleInfo  : SERVER.vehicleInfo || { plate: "", merk: "", color: "" },
    routes       : Array.isArray(SERVER.routes) ? SERVER.routes : [],   // tanpa rute awal
    fullTrip     : SERVER.fullTrip || { startLogged: false, finishLogged: false },
    initialRoute : SERVER.initialRoute || null                           // info rute awal
  };

  // === NEW: normalisasi step dari flag SSR 'arrived' + status ===
  db.routes.forEach(r => {
    if (r.status === "done") { r._step = 2; return; }        // selesai (tak ada aksi lain)
    if (r.arrived === true) { r._step = 2; return; }         // langsung tampilkan tombol "Selesai"
    if (r.status === "inprog") { r._step = 1; return; }      // tampilkan "Tiba" & "Batal"
    r._step = 0;                                             // default "Menuju"
  });

  const perPage = 5;
  let currentPage = 1;

  // === UPDATED: Mulai "started" dari DB termasuk rute yang sudah 'arrived' ===
  let started =
    (!!db.initialRoute && db.initialRoute.status === "inprog") ||
    db.routes.some(r => r.status === "inprog" || r.arrived === true);

  // ======== El ========
  const $name      = document.getElementById("driverName");
  const $veh       = document.getElementById("vehicleVal");
  const $photo     = document.getElementById("driverPhoto");

  const $initBoxDesk   = document.getElementById("initialRouteBox");
  const $initNameDesk  = document.getElementById("initialRouteName");
  const $initAddrDesk  = document.getElementById("initialRouteAddress");

  const $initBoxMob    = document.getElementById("initialRouteBoxMobile");
  const $initNameMob   = document.getElementById("initialRouteNameMobile");
  const $initAddrMob   = document.getElementById("initialRouteAddressMobile");

  const $open      = document.getElementById("statOpen");
  const $inprog    = document.getElementById("statInprog");
  const $done      = document.getElementById("statDone");

  const btnStart   = document.getElementById("btnStart");
  const fullInfo   = document.getElementById("fullTripInfo");
  const listEl     = document.getElementById("routesList");
  const pageInfo   = document.getElementById("pageInfo");
  const prevBtn    = document.getElementById("prevPage");
  const nextBtn    = document.getElementById("nextPage");

  // ======== Utils ========
  function toast(msg, type = "info") {
    const colors = {
      success: ["#0f9d58", "#e6fff2"],
      info   : ["#0d6efd", "#eef5ff"],
      warn   : ["#ff9800", "#fff6e6"],
      error  : ["#dc3545", "#ffecec"]
    };
    const id = "t" + Date.now();
    const el = document.createElement("div");
    el.className = "toast";
    el.id = id;
    el.innerHTML =
      `<div class="toast-header" style="background:${colors[type][1]};border-radius:8px">
         <strong class="mr-auto" style="color:${colors[type][0]}">Info</strong>
         <small class="text-muted ml-2">sekarang</small>
         <button type="button" class="ml-2 mb-1 close" data-dismiss="toast">&times;</button>
       </div>
       <div class="toast-body p-2">${msg}</div>`;
    document.getElementById("toastContainer").appendChild(el);
    $('#'+id).toast({ delay: 3000 }).toast('show').on('hidden.bs.toast', () => el.remove());
  }

  function api(action, payload) {
    return $.ajax({
      url: API_URL,
      method: "POST",
      dataType: "json",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      data: { action, ...payload }
    }).catch(xhr => {
      let preview = "";
      try { preview = (xhr.responseText || "").slice(0, 400); } catch {}
      toast(`Request error - HTTP ${xhr.status}\n${preview}`, "error"); // pakai '-' (minus)
      return null;
    });
  }

  function isMobile(){ return window.innerWidth <= 576; }

  function setStartButton(kind, html, disabled) {
    btnStart.disabled = !!disabled;
    btnStart.className = `btn btn-${kind} btn-start` + (isMobile() ? " btn-block" : "");
    btnStart.innerHTML = html;
  }

  function updateSummary() {
    let open=0, inpg=0, done=0;
    db.routes.forEach(r=>{
      if (r.status === "done") done++;
      else if (r.status === "inprog") inpg++;
      else open++;
    });
    $open.textContent   = open;
    $inprog.textContent = inpg;
    $done.textContent   = done;

    const allDone = db.routes.length > 0 && db.routes.every(x => x.status === "done");

    if (allDone) {
      if (!db.fullTrip.startLogged) {
        setStartButton("primary", '<i class="fas fa-truck-loading mr-2"></i>Menuju Full', false);
        fullInfo.style.display = "none";
      } else if (!db.fullTrip.finishLogged) {
        setStartButton("success", '<i class="fas fa-flag-checkered mr-2"></i>Perjalanan Selesai', false);
        fullInfo.style.display = "block";
        fullInfo.textContent = "Status: Menuju Full (dimulai)";
      } else {
        setStartButton("secondary", "Perjalanan Selesai", true);
        fullInfo.style.display = "block";
        fullInfo.textContent = "Status: Perjalanan Full selesai";
      }
      return;
    }

    if (started) {
      setStartButton("success", '<i class="fas fa-route mr-2"></i>Sedang Perjalanan', false);
      fullInfo.style.display = "none";
    } else {
      setStartButton("primary", '<i class="fas fa-play mr-2"></i>Mulai Perjalanan', false);
      fullInfo.style.display = "none";
    }
  }

  // ======== Header ========
  function initHeader() {
    $name.textContent  = db.driver.name || LOGIN_USER || "-";
    $photo.textContent = (db.driver.name || LOGIN_USER || "D").charAt(0).toUpperCase();

    const vehText = [db.vehicleInfo.plate, db.vehicleInfo.merk, db.vehicleInfo.color]
      .filter(Boolean).join(" - "); // minus biasa
    $veh.textContent = vehText || (db.initialRoute?.vehicle || db.driver.vehicle || (db.routes[0]?.vehicle || "-"));

    if (db.initialRoute) {
      $initBoxDesk?.classList.remove("d-none");
      $initNameDesk.textContent = db.initialRoute.name || "-";
      $initAddrDesk.textContent = db.initialRoute.address || "-";
      if ($initBoxMob) {
        $initBoxMob.style.display = "block";
        $initNameMob.textContent = db.initialRoute.name || "-";
        $initAddrMob.textContent = db.initialRoute.address || "-";
      }
    } else {
      $initBoxDesk?.classList.add("d-none");
      if ($initBoxMob) $initBoxMob.style.display = "none";
    }

    updateSummary();
  }

  // ======== List Routes ========
  function renderRoutes() {
    updateSummary();

    const totalPages = Math.max(1, Math.ceil(db.routes.length / perPage));
    currentPage = Math.min(Math.max(1, currentPage), totalPages);
    const startIdx = (currentPage - 1) * perPage;
    const items = db.routes.slice(startIdx, startIdx + perPage);

    listEl.innerHTML = "";
    items.forEach(r => {
      const statusClass = r.status === "open" ? "status-open" : (r.status === "inprog" ? "status-inprog" : "status-done");
      const statusLabel = r.status === "open" ? "Belum" : (r.status === "inprog" ? "Dalam Perjalanan" : "Selesai");

      const card = document.createElement("div");
      card.className = "route-card";
      card.dataset.rid = r.id;
      card.innerHTML =
        `<div class="route-left">
           <div class="route-title">${r.name}</div>
           <div class="text-muted">${r.address || "-"}</div>
           <div class="text-muted small">Tanggal: ${r.date || "-"}</div>
         </div>
         <div class="action-area"></div>
         <div style="display:flex;align-items:center">
           <div class="status-pill ${statusClass}">${statusLabel}</div>
         </div>`;

      card.addEventListener("click", (ev) => {
        if (ev.target.closest(".btn-action")) return;
        if (db.routes.every(x => x.status === "done")) return;
        if (!started) { toast("Klik Mulai Perjalanan terlebih dahulu.", "warn"); return; }
        const act = db.routes.find(x => x.status === "inprog");
        if (act && act.id !== r.id) { toast("Selesaikan rute berjalan dulu.", "warn"); return; }
        document.querySelectorAll(".route-card").forEach(c => c.classList.remove("active"));
        card.classList.toggle("active");
        fillAction(card, r);
      });

      fillAction(card, r);
      listEl.appendChild(card);
    });

    pageInfo.textContent = `${currentPage} / ${totalPages}`;
    prevBtn.disabled = currentPage <= 1;
    nextBtn.disabled = currentPage >= totalPages;
  }

  function fillAction(card, r) {
    const area = card.querySelector(".action-area");
    area.innerHTML = "";

    if (db.routes.every(x => x.status === "done")) return;
    if (r.status === "done") { area.innerHTML = '<div class="small text-success">Rute selesai</div>'; return; }

    // === UPDATED: default step mempertimbangkan 'arrived' dari SSR ===
    if (typeof r._step === "undefined") {
      r._step = r.arrived === true ? 2
           : (r.status === "inprog" ? 1
           : (r.status === "open"   ? 0 : 2));
    }

    const step = r._step;
    const size = isMobile() ? "btn-sm" : "btn-sm";

    if (step === 0) {
      const b = document.createElement("button");
      b.className = `btn btn-primary ${size} btn-action`;
      b.innerHTML = '<i class="fas fa-map-marker-alt"></i> Menuju';
      b.addEventListener("click", async (e) => {
        e.stopPropagation();
        const act = db.routes.find(x => x.status === "inprog");
        if (act && act.id !== r.id) { toast("Selesaikan rute berjalan dulu.", "warn"); return; }
        const resp = await api("update_status", { routeId: r.id, status: "inprog" });
        if (resp && resp.success) { r.status = "inprog"; r._step = 1; renderRoutes(); toast(`${r.name} - menuju`, "info"); }
      });
      area.appendChild(b);

    } else if (step === 1) {
      const tiba = document.createElement("button");
      tiba.className = `btn btn-warning ${size} btn-action mr-2`;
      tiba.innerHTML = '<i class="fas fa-check-circle"></i> Tiba';
      tiba.addEventListener("click", async (e) => {
        e.stopPropagation();
        const resp = await api("arrive_route", { routeId: r.id });
        if (resp && resp.success) {
          r.arrived = true;                 // === NEW: tandai sudah tiba di sisi client
          r._step = 2;                      // tampilkan tombol "Selesai"
          renderRoutes();
          toast("Tiba di lokasi", "info");
        }
      });

      const batal = document.createElement("button");
      batal.className = `btn btn-secondary ${size} btn-action`;
      //batal.innerHTML = '<i class="fas fa-times"></i> Batal';
      batal.addEventListener("click", async (e) => {
        e.stopPropagation();
        const resp = await api("cancel_route", { routeId: r.id });
        if (resp && resp.success) { r._step = 0; r.status = "open"; renderRoutes(); toast("Progres rute dibatalkan", "warn"); }
      });

      area.appendChild(tiba);
      //area.appendChild(batal);

    } else if (step === 2) {
      const fin = document.createElement("button");
      fin.className = `btn btn-success ${size} btn-action mr-2`;
      fin.innerHTML = '<i class="fas fa-flag-checkered"></i> Selesai';
      fin.addEventListener("click", (e) => {
        e.stopPropagation();
        // kosongkan field setiap kali modal dibuka
        $("#inputCategory").val("");
        $("#inputQty").val("");
        $("#inputNote").val("");
        $("#finishRouteId").val(r.id);
        $("#finishModal").modal("show");
      });

      const back = document.createElement("button");
      back.className = `btn btn-secondary ${size} btn-action`;
      //back.innerHTML = '<i class="fas fa-arrow-left"></i> Kembali';
      back.addEventListener("click", (e) => { e.stopPropagation(); r._step = 1; renderRoutes(); });

      area.appendChild(fin);
      //area.appendChild(back);
    }
  }

  // ======== Tombol utama dashboard ========
  btnStart.addEventListener("click", async () => {
    const allDone = db.routes.length > 0 && db.routes.every(x => x.status === "done");

    if (!allDone) {
      if (!started) {
        if (db.initialRoute?.id) {
          const resp = await api("start_route", { routeId: db.initialRoute.id });
          if (!(resp && resp.success)) { toast("Gagal mulai perjalanan (rute awal)", "error"); return; }
          db.initialRoute.status = "inprog";
        }
        started = true;
        document.querySelector(".routes-wrap").style.display = "block";
        updateSummary(); renderRoutes();
        toast("Perjalanan dimulai", "success");
      } else {
        toast("Lanjutkan menyelesaikan rute.", "info");
      }
      return;
    }

    if (!db.fullTrip.startLogged) {
      const resp = await api("start_full", { batchId: db.driver.batchId });
      if (resp && resp.success) {
        db.fullTrip.startLogged = true;
        fullInfo.style.display = "block";
        fullInfo.textContent = "Status: Menuju Full (dimulai " + (resp.startedAt || "") + ")";
        updateSummary();
        toast("Menuju FULL dimulai", "success");
      }
      return;
    }
    if (!db.fullTrip.finishLogged) {
      const r = await api("finish_full", { batchId: db.driver.batchId });
      if (r && r.success) {
        if (db.initialRoute?.id) {
          await api("update_status", { routeId: db.initialRoute.id, status: "done" });
          db.initialRoute.status = "done";
        }
        db.fullTrip.finishLogged = true;
        fullInfo.style.display = "block";
        fullInfo.textContent = "Status: Perjalanan Full selesai pada " + (r.finishedAt || "");
        updateSummary();
        toast("Perjalanan FULL selesai", "success");
      }
    }
  });

  // Submit finish modal
  document.getElementById("finishForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const rid = Number(document.getElementById("finishRouteId").value);

    const category = ($("#inputCategory").val() || "").trim();
    const qtyRaw   = ($("#inputQty").val() || "").trim();
    const note     = ($("#inputNote").val() || "").trim();

    // === VALIDASI BARU ===
    // Jika kategori dipilih, qty dan note wajib diisi
    if (category !== "") {
      // validasi qty
      const qtyNum = Number(qtyRaw);
      if (!qtyRaw || Number.isNaN(qtyNum) || qtyNum <= 0) {
        toast("Qty wajib diisi dan harus lebih dari 0 ketika kategori dipilih.", "error");
        return;
      }

      // validasi note
      if (!note) {
        toast("Note wajib diisi ketika kategori dipilih.", "error");
        return;
      }
    }

    const payload = {
      category: category,
      qty     : qtyRaw,
      note    : note
    };

    const resp = await api("finish_route", { routeId: rid, ...payload });
    if (resp && resp.success) {
      const rt = db.routes.find(x => x.id === rid);
      if (rt) { rt.status = "done"; delete rt._step; }
      $("#finishModal").modal("hide"); // reset akan dipicu oleh event 'hidden'
      renderRoutes(); updateSummary();
      toast("Rute selesai", "success");
    }
  });

  // Reset field saat modal ditutup (selalu kosong untuk rute berikutnya)
  $('#finishModal').on('hidden.bs.modal', function () {
    const f = document.getElementById("finishForm");
    if (f) f.reset();
    $("#inputCategory").val("");
    $("#inputQty").val("");
    $("#inputNote").val("");
    $("#finishRouteId").val("");
  });

  // Pagination
  prevBtn.addEventListener("click", () => { if (currentPage > 1) { currentPage--; renderRoutes(); } });
  nextBtn.addEventListener("click", () => {
    const total = Math.max(1, Math.ceil(db.routes.length / perPage));
    if (currentPage < total) { currentPage++; renderRoutes(); }
  });

  // Responsive
  function applyResponsive() {
    setStartButton(
      btnStart.classList.contains("btn-success") ? "success" :
      (btnStart.classList.contains("btn-secondary") ? "secondary" : "primary"),
      btnStart.innerHTML,
      btnStart.disabled
    );
  }
  window.addEventListener("resize", applyResponsive);

  // ======== NEW: Idle timer di sisi client (1 hari tanpa aktivitas) ========
  const IDLE_TIMEOUT_MS = 24 * 60 * 60 * 1000; // 1 hari
  let lastActivity = Date.now();
  let idleHandled  = false;

  function resetActivity() {
    lastActivity = Date.now();
  }

  ["click", "keydown", "mousemove", "scroll", "touchstart"].forEach(ev => {
    document.addEventListener(ev, resetActivity, { passive: true });
  });

  setInterval(() => {
    if (idleHandled) return;
    if (Date.now() - lastActivity > IDLE_TIMEOUT_MS) {
      idleHandled = true;
      try {
        if (window.localStorage)  localStorage.clear();
        if (window.sessionStorage) sessionStorage.clear();
        if ("caches" in window) {
          caches.keys().then(names => {
            for (const n of names) caches.delete(n);
          });
        }
      } catch (e) {
        if (window.console && console.warn) {
          console.warn("Gagal clear storage/cache on idle:", e);
        }
      }
      // Bisa ditambahkan toast jika mau, tapi langsung redirect pun boleh
      window.location.href = "/login.php";
    }
  }, 60000); // cek setiap 60 detik

  // Boot
  (function boot(){
    document.querySelector(".routes-wrap").style.display = started ? "block" : "none";
    $name.textContent = db.driver.name || LOGIN_USER || "-";
    initHeader();
    renderRoutes();
    applyResponsive();
  })();
})();

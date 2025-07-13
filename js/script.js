// Modern JavaScript for Stock Management System

// Global variables
let itemCounter = 0;
let productsData = [];
const batchesData = {};

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  initializeApp();
});

function initializeApp() {
  // Load products data
  loadProductsData();

  // Initialize event listeners
  initializeEventListeners();

  // Initialize modals
  initializeModals();

  // Initialize mobile menu
  initializeMobileMenu();

  // Set default dates
  setDefaultDates();
}

function loadProductsData() {
  // Load products from datalist
  const datalist = document.getElementById("datalistProducts");
  if (datalist) {
    productsData = Array.from(datalist.options).map((option) => ({
      id: option.dataset.id,
      name: option.value,
      sku: option.dataset.sku,
      standard_qty: Number.parseFloat(option.dataset.stdqty) || 25,
    }));
  }
}

function initializeEventListeners() {
  // Incoming transaction form listeners
  initializeIncomingFormListeners();

  // Outgoing transaction form listeners
  initializeOutgoingFormListeners();

  // 501 form listeners
  initialize501FormListeners();

  // General form listeners
  initializeGeneralListeners();
}

function initializeIncomingFormListeners() {
  const productNameInput = document.getElementById("incoming_product_name");
  const quantityKgInput = document.getElementById("incoming_quantity_kg");
  const quantitySacksInput = document.getElementById("incoming_quantity_sacks");
  const grossWeightInput = document.getElementById("incoming_gross_weight");
  const calcKgCheck = document.getElementById("calc_kg_check");
  const calcSakCheck = document.getElementById("calc_sak_check");

  if (productNameInput) {
    productNameInput.addEventListener("input", handleProductSelection);
    productNameInput.addEventListener("change", handleProductSelection);
  }

  if (quantityKgInput && quantitySacksInput) {
    quantityKgInput.addEventListener("input", () => {
      if (calcSakCheck && calcSakCheck.checked) {
        calculateSacksFromKg();
      }
      calculateLotNumber();
    });

    quantitySacksInput.addEventListener("input", () => {
      if (calcKgCheck && calcKgCheck.checked) {
        calculateKgFromSacks();
      }
      calculateLotNumber();
    });
  }

  if (grossWeightInput) {
    grossWeightInput.addEventListener("input", calculateLotNumber);
  }

  // Update checkbox IDs and logic
  const incomingCalcKgCheck = document.getElementById("incoming_calc_kg_check");
  const incomingCalcSakCheck = document.getElementById(
    "incoming_calc_sak_check"
  );

  if (incomingCalcKgCheck) {
    incomingCalcKgCheck.addEventListener("change", function () {
      if (this.checked) {
        if (incomingCalcSakCheck) incomingCalcSakCheck.checked = false;
        quantityKgInput.readOnly = true;
        quantitySacksInput.readOnly = false;
      } else {
        quantityKgInput.readOnly = false;
      }
      calculateKgFromSacks();
    });
  }

  if (incomingCalcSakCheck) {
    incomingCalcSakCheck.addEventListener("change", function () {
      if (this.checked) {
        if (incomingCalcKgCheck) incomingCalcKgCheck.checked = false;
        quantitySacksInput.readOnly = true;
        quantityKgInput.readOnly = false;
      } else {
        quantitySacksInput.readOnly = false;
      }
      calculateSacksFromKg();
    });
  }

  // Edit button listeners
  document.querySelectorAll(".edit-btn").forEach((btn) => {
    btn.addEventListener("click", handleEditIncoming);
  });
}

function initializeOutgoingFormListeners() {
  const addItemBtn = document.getElementById("addItemBtn");
  if (addItemBtn) {
    addItemBtn.addEventListener("click", addOutgoingItem);
  }

  // Edit button listeners for outgoing
  document
    .querySelectorAll('[data-bs-target="#outgoingTransactionModal"]')
    .forEach((btn) => {
      if (btn.dataset.docNumber) {
        btn.addEventListener("click", () =>
          handleEditOutgoing(btn.dataset.docNumber)
        );
      }
    });
}

function initialize501FormListeners() {
  const productSelect = document.getElementById("keluar501_product_id");
  const batchSelect = document.getElementById("keluar501_batch_select");
  const quantityInput = document.getElementById("keluar501_quantity");

  if (productSelect) {
    productSelect.addEventListener("change", load501Batches);
  }

  if (batchSelect) {
    batchSelect.addEventListener("change", update501SisaDisplay);
  }

  if (quantityInput) {
    quantityInput.addEventListener("input", validate501Quantity);
  }
}

function initializeGeneralListeners() {
  // Form validation
  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", handleFormSubmit);
  });

  // Auto-dismiss alerts
  setTimeout(() => {
    document.querySelectorAll(".alert").forEach((alert) => {
      const bsAlert = new bootstrap.Alert(alert);
      bsAlert.close();
    });
  }, 5000);
}

function initializeModals() {
  // Reset forms when modals are hidden
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("hidden.bs.modal", function () {
      const form = this.querySelector("form");
      if (form) {
        form.reset();
        resetModalState(this.id);
      }
    });
  });

  // Focus first input when modal is shown
  document.querySelectorAll(".modal").forEach((modal) => {
    modal.addEventListener("shown.bs.modal", function () {
      const firstInput = this.querySelector(
        'input:not([type="hidden"]):not([readonly]), select, textarea'
      );
      if (firstInput) {
        firstInput.focus();
      }
    });
  });
}

function initializeMobileMenu() {
  // DISABLED: Main toggle button in navbar is now always visible
  // This prevents duplicate toggle buttons and ensures consistent behavior
  const sidebar = document.getElementById("sidebar");
  if (sidebar) {
    document.addEventListener("click", (e) => {
      // Jangan tutup sidebar jika klik pada tombol toggle
      const toggleBtn = document.querySelector(".navbar-toggler");
      if (
        window.innerWidth <= 768 &&
        !sidebar.contains(e.target) &&
        !(toggleBtn && toggleBtn.contains(e.target))
      ) {
        const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
        if (offcanvas) {
          offcanvas.hide();
        }
      }
    });
  }
}

function setDefaultDates() {
  const today = new Date().toISOString().split("T")[0];
  document.querySelectorAll('input[type="date"]').forEach((input) => {
    if (!input.value) {
      input.value = today;
    }
  });
}

// Product selection handler
function handleProductSelection() {
  const input = document.getElementById("incoming_product_name");
  const hiddenInput = document.getElementById("incoming_product_id_hidden");

  if (!input || !hiddenInput) return;

  const selectedProduct = productsData.find((p) => p.name === input.value);
  if (selectedProduct) {
    hiddenInput.value = selectedProduct.id;

    // Auto-fill batch number with current date + product code
    const batchInput = document.getElementById("incoming_batch_number");
    if (batchInput && !batchInput.value) {
      const today = new Date();
      const dateStr =
        today.getFullYear().toString().substr(-2) +
        String(today.getMonth() + 1).padStart(2, "0") +
        String(today.getDate()).padStart(2, "0");
      batchInput.value = `${selectedProduct.sku}-${dateStr}`;
    }
  } else {
    hiddenInput.value = "";
  }
}

// Calculation functions
function calculateSacksFromKg() {
  const kgInput = document.getElementById("incoming_quantity_kg");
  const sacksInput = document.getElementById("incoming_quantity_sacks");
  const productNameInput = document.getElementById("incoming_product_name");

  if (!kgInput || !sacksInput || !productNameInput) return;

  const selectedProduct = productsData.find(
    (p) => p.name === productNameInput.value
  );
  if (selectedProduct && kgInput.value) {
    const kg = Number.parseFloat(kgInput.value);
    const standardQty = selectedProduct.standard_qty;
    const sacks = (kg / standardQty).toFixed(2);
    sacksInput.value = sacks;
  }
}

function calculateKgFromSacks() {
  const kgInput = document.getElementById("incoming_quantity_kg");
  const sacksInput = document.getElementById("incoming_quantity_sacks");
  const productNameInput = document.getElementById("incoming_product_name");

  if (!kgInput || !sacksInput || !productNameInput) return;

  const selectedProduct = productsData.find(
    (p) => p.name === productNameInput.value
  );
  if (selectedProduct && sacksInput.value) {
    const sacks = Number.parseFloat(sacksInput.value);
    const standardQty = selectedProduct.standard_qty;
    const kg = (sacks * standardQty).toFixed(2);
    kgInput.value = kg;
  }
}

function calculateLotNumber() {
  const grossWeightInput = document.getElementById("incoming_gross_weight");
  const quantityKgInput = document.getElementById("incoming_quantity_kg");
  const lotDisplay = document.getElementById("incoming_lot_number_display");

  if (!grossWeightInput || !quantityKgInput || !lotDisplay) return;

  const grossWeight = Number.parseFloat(grossWeightInput.value) || 0;
  const netWeight = Number.parseFloat(quantityKgInput.value) || 0;

  if (grossWeight > 0 && netWeight > 0) {
    const lot = grossWeight - netWeight;
    lotDisplay.value = lot.toFixed(2);
  } else {
    lotDisplay.value = "";
  }
}

// Edit handlers
function handleEditIncoming(event) {
  const btn = event.currentTarget;
  const modal = document.getElementById("incomingTransactionModal");
  const modalTitle = modal.querySelector(".modal-title");
  const submitBtn = document.getElementById("incomingSubmitButton");

  // Update modal title and button
  modalTitle.innerHTML =
    '<i class="bi bi-pencil-square me-2"></i>Edit Transaksi Barang Masuk';
  submitBtn.innerHTML = '<i class="bi bi-save-fill me-1"></i>Update Data';

  // Fill form with data
  const fields = [
    "id",
    "product_id",
    "product_name",
    "po_number",
    "supplier",
    "produsen",
    "license_plate",
    "quantity_kg",
    "quantity_sacks",
    "document_number",
    "batch_number",
    "lot_number",
    "transaction_date",
    "status",
  ];

  fields.forEach((field) => {
    const input = document.getElementById(
      `incoming_${
        field === "id"
          ? "transaction_id"
          : field === "product_id"
          ? "product_id_hidden"
          : field
      }`
    );
    if (input && btn.dataset[field]) {
      input.value = btn.dataset[field];
    }
  });

  // Calculate and display lot number
  const lotDisplay = document.getElementById("incoming_lot_number_display");
  if (lotDisplay && btn.dataset.lot_number) {
    lotDisplay.value = btn.dataset.lot_number;
  }
}

function handleEditOutgoing(docNumber) {
  const modal = document.getElementById("outgoingTransactionModal");
  const modalTitle = modal.querySelector(".modal-title");
  const submitBtn = document.getElementById("outgoingSubmitButton");
  const docInput = document.getElementById("outgoing_document_number_hidden");

  // Update modal for edit mode
  modalTitle.innerHTML =
    '<i class="bi bi-pencil-square me-2"></i>Edit Transaksi Barang Keluar';
  submitBtn.innerHTML = '<i class="bi bi-save-fill me-1"></i>Update Transaksi';

  if (docInput) {
    docInput.value = docNumber;
  }

  // Load existing transaction data
  loadOutgoingTransactionData(docNumber);
}

// Outgoing transaction functions
function addOutgoingItem() {
  itemCounter++;
  const container = document.getElementById("itemsContainer");
  if (!container) return;

  const itemHtml = createOutgoingItemHtml(itemCounter);
  container.insertAdjacentHTML("beforeend", itemHtml);

  // Initialize new item listeners
  initializeOutgoingItemListeners(itemCounter);
}

function createOutgoingItemHtml(counter) {
  return `
        <div class="card mb-3 item-card" id="item_${counter}">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-box me-1"></i>Item #${counter}
                </h6>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeOutgoingItem(${counter})">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nama Barang</label>
                        <select class="form-select product-select" name="items[${counter}][product_id]" required>
                            <option value="">-- Pilih Produk --</option>
                            ${productsData
                              .map(
                                (p) =>
                                  `<option value="${p.id}">${p.name} (${p.sku})</option>`
                              )
                              .join("")}
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Batch</label>
                        <select class="form-select batch-select" name="items[${counter}][batch_number]" required disabled>
                            <option value="">-- Pilih produk dulu --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Qty (Kg)</label>
                        <input type="number" step="any" class="form-control quantity-input" name="items[${counter}][quantity_kg]" placeholder="0.00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Qty (Sak)</label>
                        <input type="number" step="any" class="form-control sacks-input" name="items[${counter}][quantity_sacks]" placeholder="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">501 (Lot)</label>
                        <input type="number" step="any" class="form-control lot-input" name="items[${counter}][lot_number]" placeholder="0.00">
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info py-2 mb-0 stock-info" style="display: none;">
                            <small><i class="bi bi-info-circle me-1"></i>Stok tersedia: <span class="stock-amount">0</span> Kg</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function initializeOutgoingItemListeners(counter) {
  const itemCard = document.getElementById(`item_${counter}`);
  if (!itemCard) return;

  const productSelect = itemCard.querySelector(".product-select");
  const batchSelect = itemCard.querySelector(".batch-select");
  const quantityInput = itemCard.querySelector(".quantity-input");
  const sacksInput = itemCard.querySelector(".sacks-input");

  if (productSelect) {
    productSelect.addEventListener("change", () => loadBatchesForItem(counter));
  }

  if (batchSelect) {
    batchSelect.addEventListener("change", () => updateStockInfo(counter));
  }

  if (quantityInput) {
    quantityInput.addEventListener("input", () => {
      calculateSacksForItem(counter);
      validateStockForItem(counter);
    });
  }

  if (sacksInput) {
    sacksInput.addEventListener("input", () => calculateKgForItem(counter));
  }
}

function removeOutgoingItem(counter) {
  const item = document.getElementById(`item_${counter}`);
  if (item) {
    item.remove();
  }
}

function loadBatchesForItem(counter) {
  const itemCard = document.getElementById(`item_${counter}`);
  if (!itemCard) return;

  const productSelect = itemCard.querySelector(".product-select");
  const batchSelect = itemCard.querySelector(".batch-select");

  if (!productSelect || !batchSelect) return;

  const productId = productSelect.value;
  if (!productId) {
    batchSelect.innerHTML = '<option value="">-- Pilih produk dulu --</option>';
    batchSelect.disabled = true;
    return;
  }

  // Load batches via AJAX
  fetch(`api_get_batches.php?product_id=${productId}`)
    .then((response) => response.json())
    .then((data) => {
      batchSelect.innerHTML = '<option value="">-- Pilih Batch --</option>';
      data.forEach((batch) => {
        const option = document.createElement("option");
        option.value = batch.batch_number;
        option.textContent = `${batch.batch_number} (Stok: ${batch.remaining_stock} Kg)`;
        option.dataset.stock = batch.remaining_stock;
        batchSelect.appendChild(option);
      });
      batchSelect.disabled = false;
    })
    .catch((error) => {
      console.error("Error loading batches:", error);
      batchSelect.innerHTML = '<option value="">Error loading batches</option>';
    });
}

function updateStockInfo(counter) {
  const itemCard = document.getElementById(`item_${counter}`);
  if (!itemCard) return;

  const batchSelect = itemCard.querySelector(".batch-select");
  const stockInfo = itemCard.querySelector(".stock-info");
  const stockAmount = itemCard.querySelector(".stock-amount");

  if (!batchSelect || !stockInfo || !stockAmount) return;

  const selectedOption = batchSelect.options[batchSelect.selectedIndex];
  if (selectedOption && selectedOption.dataset.stock) {
    const stock = Number.parseFloat(selectedOption.dataset.stock);
    stockAmount.textContent = stock.toLocaleString("id-ID", {
      minimumFractionDigits: 2,
    });
    stockInfo.style.display = "block";
  } else {
    stockInfo.style.display = "none";
  }
}

function calculateSacksForItem(counter) {
  const itemCard = document.getElementById(`item_${counter}`);
  if (!itemCard) return;

  const productSelect = itemCard.querySelector(".product-select");
  const quantityInput = itemCard.querySelector(".quantity-input");
  const sacksInput = itemCard.querySelector(".sacks-input");

  if (!productSelect || !quantityInput || !sacksInput) return;

  const productId = productSelect.value;
  const selectedProduct = productsData.find((p) => p.id === productId);

  if (selectedProduct && quantityInput.value) {
    const kg = Number.parseFloat(quantityInput.value);
    const standardQty = selectedProduct.standard_qty;
    const sacks = (kg / standardQty).toFixed(2);
    sacksInput.value = sacks;
  }
}

function calculateKgForItem(counter) {
  const itemCard = document.getElementById(`item_${counter}`);
  if (!itemCard) return;

  const productSelect = itemCard.querySelector(".product-select");
  const quantityInput = itemCard.querySelector(".quantity-input");
  const sacksInput = itemCard.querySelector(".sacks-input");

  if (!productSelect || !quantityInput || !sacksInput) return;

  const productId = productSelect.value;
  const selectedProduct = productsData.find((p) => p.id === productId);

  if (selectedProduct && sacksInput.value) {
    const sacks = Number.parseFloat(sacksInput.value);
    const standardQty = selectedProduct.standard_qty;
    const kg = (sacks * standardQty).toFixed(2);
    quantityInput.value = kg;
  }
}

function validateStockForItem(counter) {
  const itemCard = document.getElementById(`item_${counter}`);
  if (!itemCard) return;

  const batchSelect = itemCard.querySelector(".batch-select");
  const quantityInput = itemCard.querySelector(".quantity-input");
  const stockInfo = itemCard.querySelector(".stock-info");

  if (!batchSelect || !quantityInput || !stockInfo) return;

  const selectedOption = batchSelect.options[batchSelect.selectedIndex];
  const requestedQty = Number.parseFloat(quantityInput.value) || 0;

  if (selectedOption && selectedOption.dataset.stock) {
    const availableStock = Number.parseFloat(selectedOption.dataset.stock);

    if (requestedQty > availableStock) {
      stockInfo.className = "alert alert-warning py-2 mb-0 stock-info";
      stockInfo.innerHTML = `<small><i class="bi bi-exclamation-triangle me-1"></i>Peringatan: Qty melebihi stok tersedia (${availableStock.toLocaleString(
        "id-ID",
        { minimumFractionDigits: 2 }
      )} Kg)</small>`;
      quantityInput.classList.add("is-invalid");
    } else {
      stockInfo.className = "alert alert-info py-2 mb-0 stock-info";
      stockInfo.innerHTML = `<small><i class="bi bi-info-circle me-1"></i>Stok tersedia: ${availableStock.toLocaleString(
        "id-ID",
        { minimumFractionDigits: 2 }
      )} Kg</small>`;
      quantityInput.classList.remove("is-invalid");
    }
    stockInfo.style.display = "block";
  }
}

// 501 functions
function load501Batches() {
  const productSelect = document.getElementById("keluar501_product_id");
  const batchSelect = document.getElementById("keluar501_batch_select");

  if (!productSelect || !batchSelect) return;

  const productId = productSelect.value;
  if (!productId) {
    batchSelect.innerHTML =
      '<option value="">-- Pilih produk terlebih dahulu --</option>';
    batchSelect.disabled = true;
    return;
  }

  // Load batches with 501 > 0
  fetch(`api_get_batches_501.php?product_id=${productId}`)
    .then((response) => response.json())
    .then((data) => {
      batchSelect.innerHTML = '<option value="">-- Pilih Batch --</option>';
      data.forEach((batch) => {
        const option = document.createElement("option");
        option.value = batch.batch_number;
        option.textContent = `${batch.batch_number} (Sisa 501: ${batch.remaining_501} Kg)`;
        option.dataset.sisa501 = batch.remaining_501;
        batchSelect.appendChild(option);
      });
      batchSelect.disabled = false;
    })
    .catch((error) => {
      console.error("Error loading 501 batches:", error);
      batchSelect.innerHTML = '<option value="">Error loading batches</option>';
    });
}

function update501SisaDisplay() {
  const batchSelect = document.getElementById("keluar501_batch_select");
  const sisaDisplay = document.getElementById("keluar501_sisa_display");

  if (!batchSelect || !sisaDisplay) return;

  const selectedOption = batchSelect.options[batchSelect.selectedIndex];
  if (selectedOption && selectedOption.dataset.sisa501) {
    const sisa = Number.parseFloat(selectedOption.dataset.sisa501);
    sisaDisplay.value =
      sisa.toLocaleString("id-ID", { minimumFractionDigits: 2 }) + " Kg";
  } else {
    sisaDisplay.value = "0 Kg";
  }
}

function validate501Quantity() {
  const batchSelect = document.getElementById("keluar501_batch_select");
  const quantityInput = document.getElementById("keluar501_quantity");

  if (!batchSelect || !quantityInput) return;

  const selectedOption = batchSelect.options[batchSelect.selectedIndex];
  const requestedQty = Number.parseFloat(quantityInput.value) || 0;

  if (selectedOption && selectedOption.dataset.sisa501) {
    const availableSisa = Number.parseFloat(selectedOption.dataset.sisa501);

    if (requestedQty > availableSisa) {
      quantityInput.classList.add("is-invalid");
      quantityInput.setCustomValidity(`Maksimum ${availableSisa} Kg`);
    } else {
      quantityInput.classList.remove("is-invalid");
      quantityInput.setCustomValidity("");
    }
  }
}

// Utility functions
function resetModalState(modalId) {
  switch (modalId) {
    case "incomingTransactionModal":
      const incomingTitle = document.querySelector(
        "#incomingTransactionModal .modal-title"
      );
      const incomingSubmitBtn = document.getElementById("incomingSubmitButton");
      if (incomingTitle) {
        incomingTitle.innerHTML =
          '<i class="bi bi-plus-circle-fill me-2"></i>Tambah Transaksi Barang Masuk';
      }
      if (incomingSubmitBtn) {
        incomingSubmitBtn.innerHTML =
          '<i class="bi bi-save-fill me-1"></i>Simpan Data';
      }
      break;

    case "outgoingTransactionModal":
      const outgoingTitle = document.querySelector(
        "#outgoingTransactionModal .modal-title"
      );
      const outgoingSubmitBtn = document.getElementById("outgoingSubmitButton");
      if (outgoingTitle) {
        outgoingTitle.innerHTML =
          '<i class="bi bi-plus-circle-fill me-2"></i>Tambah Transaksi Barang Keluar';
      }
      if (outgoingSubmitBtn) {
        outgoingSubmitBtn.innerHTML =
          '<i class="bi bi-save-fill me-1"></i>Simpan Transaksi';
      }

      // Clear items container
      const itemsContainer = document.getElementById("itemsContainer");
      if (itemsContainer) {
        itemsContainer.innerHTML = "";
      }
      itemCounter = 0;

      // Add first item for new transaction
      if (!document.getElementById("outgoing_document_number_hidden").value) {
        addOutgoingItem();
      }
      break;
  }
}

function handleFormSubmit(event) {
  const form = event.target;
  const submitBtn = form.querySelector('button[type="submit"]');

  if (submitBtn) {
    submitBtn.disabled = true;
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML =
      '<i class="bi bi-hourglass-split me-1"></i>Menyimpan...';

    // Re-enable button after 3 seconds to prevent permanent disable
    setTimeout(() => {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
    }, 3000);
  }
}

function loadOutgoingTransactionData(docNumber) {
  // Load existing transaction data via AJAX
  fetch(
    `api_get_outgoing_details.php?document_number=${encodeURIComponent(
      docNumber
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.transaction) {
        const transaction = data.transaction;

        // Fill header data
        document.getElementById("outgoing_transaction_date").value =
          transaction.transaction_date;
        document.getElementById("outgoing_document_number").value =
          transaction.document_number;
        document.getElementById("outgoing_status").value = transaction.status;

        // Clear and populate items
        const itemsContainer = document.getElementById("itemsContainer");
        itemsContainer.innerHTML = "";
        itemCounter = 0;

        transaction.items.forEach((item, index) => {
          addOutgoingItem();
          const currentCounter = itemCounter;
          const itemCard = document.getElementById(`item_${currentCounter}`);

          if (itemCard) {
            // Fill item data
            const productSelect = itemCard.querySelector(".product-select");
            const quantityInput = itemCard.querySelector(".quantity-input");
            const sacksInput = itemCard.querySelector(".sacks-input");
            const lotInput = itemCard.querySelector(".lot-input");

            if (productSelect) productSelect.value = item.product_id;
            if (quantityInput) quantityInput.value = item.quantity_kg;
            if (sacksInput) sacksInput.value = item.quantity_sacks;
            if (lotInput) lotInput.value = item.lot_number;

            // Load batches and set selected batch
            loadBatchesForItem(currentCounter).then(() => {
              const batchSelect = itemCard.querySelector(".batch-select");
              if (batchSelect) batchSelect.value = item.batch_number;
            });
          }
        });
      }
    })
    .catch((error) => {
      console.error("Error loading transaction data:", error);
    });
}

// Initialize first outgoing item when modal is shown for new transaction
document
  .getElementById("outgoingTransactionModal")
  ?.addEventListener("shown.bs.modal", () => {
    const docNumberHidden = document.getElementById(
      "outgoing_document_number_hidden"
    );
    const itemsContainer = document.getElementById("itemsContainer");

    // Only add first item for new transactions
    if (!docNumberHidden.value && itemsContainer.children.length === 0) {
      addOutgoingItem();
    }
  });

// Responsive table handling
function handleResponsiveTables() {
  const tables = document.querySelectorAll(".table-responsive");
  tables.forEach((table) => {
    if (table.scrollWidth > table.clientWidth) {
      table.classList.add("table-scroll-indicator");
    }
  });
}

// Call responsive table handler on window resize
window.addEventListener("resize", handleResponsiveTables);
window.addEventListener("load", handleResponsiveTables);

// Export functions for global access
window.removeOutgoingItem = removeOutgoingItem;
window.addOutgoingItem = addOutgoingItem;
document.addEventListener("DOMContentLoaded", () => {
  // Helper function untuk format angka di JS
  function formatAngkaJS(angka) {
    if (angka === null || isNaN(Number.parseFloat(angka))) return "";
    const nomor = Number.parseFloat(angka);
    // Mengganti titik desimal dengan koma
    return nomor.toString().replace(".", ",");
  }

  // --- LOGIKA UNTUK HALAMAN DAFTAR PRODUK ---
  const productModalEl = document.getElementById("productModal");
  if (productModalEl) {
    const modalForm = document.getElementById("productForm");
    const modalTitle = document.getElementById("productModalLabel");
    const submitButton = document.getElementById("productSubmitButton");
    const productIdInput = document.getElementById("product_id_input");

    productModalEl.addEventListener("show.bs.modal", (event) => {
      const button = event.relatedTarget;
      modalForm.reset();

      if (button.classList.contains("edit-btn")) {
        modalTitle.textContent = "Edit Produk";
        submitButton.innerHTML =
          '<i class="bi bi-save-fill me-2"></i>Simpan Perubahan';
        productIdInput.value = button.dataset.id;
        document.getElementById("product_name").value =
          button.dataset.product_name;
        document.getElementById("sku").value = button.dataset.sku;
        document.getElementById("standard_qty").value =
          button.dataset.standard_qty;
      } else {
        modalTitle.textContent = "Tambah Produk Baru";
        submitButton.innerHTML =
          '<i class="bi bi-plus-circle-fill me-2"></i>Tambah Produk';
        productIdInput.value = "";
      }
    });
  }

  // --- LOGIKA UNTUK MODAL BARANG MASUK ---
  const incomingModalEl = document.getElementById("incomingTransactionModal");
  if (incomingModalEl) {
    const modalTitle = document.getElementById("incomingModalLabel");
    const submitButton = document.getElementById("incomingSubmitButton");
    const modalForm = document.getElementById("incomingTransactionForm");

    const transactionIdInput = document.getElementById(
      "incoming_transaction_id"
    );
    const productNameInput = document.getElementById("incoming_product_name");
    const productIdHidden = document.getElementById(
      "incoming_product_id_hidden"
    );
    const productDatalist = document.getElementById("datalistProducts");
    const qtyKgInput = document.getElementById("incoming_quantity_kg");
    const qtySakInput = document.getElementById("incoming_quantity_sacks");
    const calcKgCheck = document.getElementById("incoming_calc_kg_check");
    const calcSakCheck = document.getElementById("incoming_calc_sak_check");
    const grossWeightInput = document.getElementById("incoming_gross_weight");
    const lotNumberDisplay = document.getElementById(
      "incoming_lot_number_display"
    );

    let currentStdQty = 0;

    function updateStdQty() {
      const selectedOption = Array.from(productDatalist.options).find(
        (opt) => opt.value === productNameInput.value
      );
      if (selectedOption) {
        currentStdQty = Number.parseFloat(selectedOption.dataset.stdqty) || 0;
        productIdHidden.value = selectedOption.dataset.id;
      } else {
        currentStdQty = 0;
        productIdHidden.value = "";
      }
    }

    function autoCalculate() {
      const qtyKg = Number.parseFloat(qtyKgInput.value);
      const qtySak = Number.parseFloat(qtySakInput.value);

      if (calcSakCheck.checked && currentStdQty > 0 && !isNaN(qtyKg)) {
        qtySakInput.value = (qtyKg / currentStdQty).toFixed(2);
      } else if (calcKgCheck.checked && currentStdQty > 0 && !isNaN(qtySak)) {
        qtyKgInput.value = (qtySak * currentStdQty).toFixed(2);
      }
    }

    function calculateLotNumber() {
      const gross = Number.parseFloat(grossWeightInput.value);
      const net = Number.parseFloat(qtyKgInput.value);
      if (!isNaN(gross) && !isNaN(net)) {
        lotNumberDisplay.value = (gross - net).toFixed(2);
      } else {
        lotNumberDisplay.value = "";
      }
    }

    productNameInput.addEventListener("input", updateStdQty);
    qtyKgInput.addEventListener("input", () => {
      autoCalculate();
      calculateLotNumber();
    });
    qtySakInput.addEventListener("input", autoCalculate);
    grossWeightInput.addEventListener("input", calculateLotNumber);

    calcKgCheck.addEventListener("change", function () {
      if (this.checked) {
        calcSakCheck.checked = false;
        qtyKgInput.readOnly = true;
        qtySakInput.readOnly = false;
      } else {
        qtyKgInput.readOnly = false;
      }
      autoCalculate();
    });

    calcSakCheck.addEventListener("change", function () {
      if (this.checked) {
        calcKgCheck.checked = false;
        qtySakInput.readOnly = true;
        qtyKgInput.readOnly = false;
      } else {
        qtySakInput.readOnly = false;
      }
      autoCalculate();
    });

    incomingModalEl.addEventListener("show.bs.modal", (event) => {
      const button = event.relatedTarget;
      modalForm.reset();
      transactionIdInput.value = "";
      qtyKgInput.readOnly = false;
      qtySakInput.readOnly = false;

      if (button && button.classList.contains("edit-btn")) {
        modalTitle.textContent = "Edit Transaksi Barang Masuk";
        submitButton.innerHTML =
          '<i class="bi bi-save-fill me-2"></i>Simpan Perubahan';

        // Mengisi form dengan data dari tombol edit
        transactionIdInput.value = button.dataset.id;
        productIdHidden.value = button.dataset.product_id;
        productNameInput.value = button.dataset.product_name;
        document.getElementById("incoming_transaction_date").value =
          button.dataset.transaction_date;
        document.getElementById("incoming_po_number").value =
          button.dataset.po_number;
        document.getElementById("incoming_supplier").value =
          button.dataset.supplier;
        document.getElementById("incoming_produsen").value =
          button.dataset.produsen;
        document.getElementById("incoming_quantity_kg").value =
          button.dataset.quantity_kg;
        document.getElementById("incoming_quantity_sacks").value =
          button.dataset.quantity_sacks;
        document.getElementById("incoming_document_number").value =
          button.dataset.document_number;
        document.getElementById("incoming_batch_number").value =
          button.dataset.batch_number;
        document.getElementById("incoming_license_plate").value =
          button.dataset.license_plate;
        document.getElementById("incoming_status").value =
          button.dataset.status;

        // Trigger kalkulasi ulang
        updateStdQty();
        calculateLotNumber();
      } else {
        modalTitle.textContent = "Tambah Transaksi Barang Masuk";
        submitButton.innerHTML =
          '<i class="bi bi-plus-circle-fill me-2"></i>Tambah Data';
        // Set tanggal hari ini untuk form tambah baru
        document.getElementById("incoming_transaction_date").value = new Date()
          .toISOString()
          .slice(0, 10);
      }
    });
  }
  // --- LOGIKA UNTUK HALAMAN BARANG KELUAR ---
  const outgoingModalEl = document.getElementById("outgoingTransactionModal");
  if (outgoingModalEl) {
    let outgoingItems = [];
    let batchCache = {};

    const modalTitle = document.getElementById("outgoingModalLabel");
    const itemProductNameInput = document.getElementById(
      "item_product_name_outgoing"
    );
    const itemProductIdHidden = document.getElementById(
      "item_product_id_hidden"
    );
    const itemProductDatalist = document.getElementById(
      "datalistProductsOutgoing"
    );
    const itemIncomingSelect = document.getElementById("item_incoming_id");
    const itemQtyKg = document.getElementById("item_quantity_kg");
    const itemQtySacks = document.getElementById("item_quantity_sacks");
    const addItemBtn = document.getElementById("addItemBtn");
    const itemsListTbody = document.getElementById("outgoing_items_list");
    const mainForm = document.getElementById("outgoingTransactionForm");
    const hiddenJsonInput = document.getElementById("items_json");
    const originalDocInput = document.getElementById(
      "original_document_number"
    );

    // PERBAIKAN: Checkbox yang benar untuk barang keluar
    const outgoingCalcKgCheck = document.getElementById(
      "outgoing_calc_kg_check"
    ); // Menghitung KG (readonly) dari SAK
    const outgoingCalcSakCheck = document.getElementById(
      "outgoing_calc_sak_check"
    ); // Menghitung SAK (readonly) dari KG
    let outgoingCurrentStdQty = 0;

    function renderBatchDropdown(productId) {
      itemIncomingSelect.innerHTML =
        '<option value="" selected>-- Pilih Batch --</option>';
      if (batchCache[productId] && batchCache[productId].length > 0) {
        let availableBatches = 0;
        batchCache[productId].forEach((batch) => {
          const reservedQty = outgoingItems
            .filter((item) => item.incoming_id == batch.id)
            .reduce((sum, item) => sum + Number.parseFloat(item.qty_kg), 0);
          const currentSisa =
            Number.parseFloat(batch.sisa_stok_kg) - reservedQty;

          if (currentSisa > 0) {
            const sisa_kg_formatted = formatAngkaJS(currentSisa);
            const optionText = `Tgl: ${batch.transaction_date} - Batch: ${
              batch.batch_number || "N/A"
            } (Sisa: ${sisa_kg_formatted} Kg)`;
            itemIncomingSelect.innerHTML += `<option value="${
              batch.id
            }" data-sisa_kg="${currentSisa}" data-batch_number="${
              batch.batch_number || ""
            }">${optionText}</option>`;
            availableBatches++;
          }
        });
        if (availableBatches === 0) {
          itemIncomingSelect.innerHTML =
            '<option value="">-- Stok batch habis --</option>';
        }
      } else {
        itemIncomingSelect.innerHTML =
          '<option value="">-- Tidak ada batch tersedia --</option>';
      }
      itemIncomingSelect.disabled = false;
    }

    function handleProductChange() {
      const selectedOption = Array.from(itemProductDatalist.options).find(
        (opt) => opt.value === itemProductNameInput.value
      );
      let productId = "";

      if (selectedOption) {
        productId = selectedOption.dataset.id;
        itemProductIdHidden.value = productId;
        outgoingCurrentStdQty =
          Number.parseFloat(selectedOption.dataset.stdqty) || 0;
      } else {
        itemProductIdHidden.value = "";
        outgoingCurrentStdQty = 0;
      }

      itemIncomingSelect.innerHTML = '<option value="">Memuat...</option>';
      itemIncomingSelect.disabled = true;

      if (!productId) {
        itemIncomingSelect.innerHTML =
          '<option value="">-- Pilih Barang dulu --</option>';
        itemIncomingSelect.disabled = true;
        return;
      }

      if (batchCache[productId]) {
        renderBatchDropdown(productId);
      } else {
        fetch(`api_get_batches.php?product_id=${productId}`)
          .then((response) => response.json())
          .then((data) => {
            batchCache[productId] = data;
            renderBatchDropdown(productId);
          })
          .catch((error) => {
            console.error("Error fetching batches:", error);
            itemIncomingSelect.innerHTML =
              '<option value="">Gagal memuat batch.</option>';
            itemIncomingSelect.disabled = false;
          });
      }
    }

    itemProductNameInput.addEventListener("input", handleProductChange);

    function autoCalculateOutgoing() {
      const qtyKg = Number.parseFloat(itemQtyKg.value);
      const qtySak = Number.parseFloat(itemQtySacks.value);

      if (
        outgoingCalcSakCheck.checked &&
        outgoingCurrentStdQty > 0 &&
        !isNaN(qtyKg)
      ) {
        itemQtySacks.value = (qtyKg / outgoingCurrentStdQty).toFixed(2);
      } else if (
        outgoingCalcKgCheck.checked &&
        outgoingCurrentStdQty > 0 &&
        !isNaN(qtySak)
      ) {
        itemQtyKg.value = (qtySak * outgoingCurrentStdQty).toFixed(2);
      }
    }

    itemQtyKg.addEventListener("input", autoCalculateOutgoing);
    itemQtySacks.addEventListener("input", autoCalculateOutgoing);

    // PERBAIKAN: Logika readonly yang benar
    outgoingCalcKgCheck.addEventListener("change", function () {
      if (this.checked) {
        outgoingCalcSakCheck.checked = false;
        itemQtyKg.readOnly = true; // Kunci input KG
        itemQtySacks.readOnly = false;
      } else {
        itemQtyKg.readOnly = false;
      }
      autoCalculateOutgoing();
    });

    outgoingCalcSakCheck.addEventListener("change", function () {
      if (this.checked) {
        outgoingCalcKgCheck.checked = false;
        itemQtySacks.readOnly = true; // Kunci input SAK
        itemQtyKg.readOnly = false;
      } else {
        itemQtySacks.readOnly = false;
      }
      autoCalculateOutgoing();
    });

    function renderItemsTable() {
      itemsListTbody.innerHTML = "";
      if (outgoingItems.length === 0) {
        itemsListTbody.innerHTML =
          '<tr><td colspan="6" class="text-center text-muted">Belum ada item yang ditambahkan.</td></tr>';
        return;
      }
      outgoingItems.forEach((item, index) => {
        const row = `<tr><td>${index + 1}</td><td class="text-start">${
          item.product_name
        }<br><small class="text-muted">${item.sku || ""}</small></td><td>${
          item.batch_number
        }</td><td>${formatAngkaJS(item.qty_kg)}</td><td>${formatAngkaJS(
          item.qty_sak
        )}</td><td><button type="button" class="btn btn-danger btn-sm" data-index="${index}"><i class="bi bi-trash3-fill"></i></button></td></tr>`;
        itemsListTbody.innerHTML += row;
      });
    }

    addItemBtn.addEventListener("click", () => {
      const productOption = Array.from(itemProductDatalist.options).find(
        (opt) => opt.value === itemProductNameInput.value
      );
      const batchOption =
        itemIncomingSelect.options[itemIncomingSelect.selectedIndex];
      const qtyKgDiminta = Number.parseFloat(itemQtyKg.value);

      if (
        !productOption ||
        !productOption.dataset.id ||
        !batchOption.value ||
        !batchOption.dataset.sisa_kg
      ) {
        Swal.fire(
          "Oops...",
          "Harap pilih Nama Barang dan Batch yang valid.",
          "warning"
        );
        return;
      }
      if (isNaN(qtyKgDiminta) || qtyKgDiminta <= 0) {
        Swal.fire("Oops...", "Harap masukkan Qty (Kg) yang valid.", "warning");
        return;
      }

      const sisaStok = Number.parseFloat(batchOption.dataset.sisa_kg);
      let qtyToAdd = qtyKgDiminta;

      if (qtyKgDiminta > sisaStok) {
        qtyToAdd = sisaStok;
        const kekurangan = qtyKgDiminta - sisaStok;
        Swal.fire({
          title: "Stok Tidak Cukup",
          text: `Hanya ${formatAngkaJS(
            sisaStok
          )} Kg yang ditambahkan. Kekurangan ${formatAngkaJS(kekurangan)} Kg.`,
          icon: "info",
        });
      }

      if (qtyToAdd > 0) {
        const stdQty = Number.parseFloat(productOption.dataset.stdqty);
        const qtySakToAdd = stdQty > 0 ? qtyToAdd / stdQty : 0;

        outgoingItems.push({
          product_id: productOption.dataset.id,
          product_name: productOption.value,
          sku: productOption.dataset.sku,
          incoming_id: batchOption.value,
          batch_number: batchOption.dataset.batch_number,
          qty_kg: qtyToAdd,
          qty_sak: Number.parseFloat(qtySakToAdd.toFixed(2)),
        });
        renderItemsTable();
        renderBatchDropdown(productOption.dataset.id);
      }

      itemQtyKg.value = "";
      itemQtySacks.value = "";
      itemProductNameInput.value = "";
      outgoingCalcKgCheck.checked = false;
      outgoingCalcSakCheck.checked = false;
      itemQtyKg.readOnly = false;
      itemQtySacks.readOnly = false;
      itemIncomingSelect.innerHTML =
        '<option value="">-- Pilih Barang dulu --</option>';
      itemIncomingSelect.disabled = true;
    });

    itemsListTbody.addEventListener("click", (e) => {
      const deleteButton = e.target.closest("button");
      if (deleteButton && deleteButton.dataset.index) {
        const indexToRemove = Number.parseInt(deleteButton.dataset.index, 10);
        const itemToRemove = outgoingItems[indexToRemove];
        outgoingItems.splice(indexToRemove, 1);
        renderItemsTable();
        renderBatchDropdown(itemToRemove.product_id);
      }
    });

    outgoingModalEl.addEventListener("show.bs.modal", (event) => {
      const button = event.relatedTarget;
      mainForm.reset();
      outgoingItems = [];
      batchCache = {};
      originalDocInput.value = "";
      renderItemsTable();
      itemIncomingSelect.innerHTML =
        '<option value="">-- Pilih Barang dulu --</option>';
      itemIncomingSelect.disabled = true;
      itemQtyKg.readOnly = false;
      itemQtySacks.readOnly = false;
      outgoingCalcKgCheck.checked = false;
      outgoingCalcSakCheck.checked = false;

      if (button && button.classList.contains("edit-btn")) {
        modalTitle.innerHTML =
          '<i class="bi bi-pencil-square me-2"></i>Edit Transaksi Barang Keluar';
        const docNumber = button.dataset.docNumber;
        if (!docNumber) {
          bootstrap.Modal.getInstance(outgoingModalEl).hide();
          Swal.fire(
            "Aksi Dibatalkan",
            "Transaksi tanpa nomor dokumen tidak dapat diedit.",
            "error"
          );
          return;
        }

        originalDocInput.value = docNumber;
        fetch(`api_get_outgoing_details.php?doc_number=${docNumber}`)
          .then((response) => response.json())
          .then((data) => {
            if (data.error) {
              Swal.fire("Error!", data.error, "error");
              bootstrap.Modal.getInstance(outgoingModalEl).hide();
              return;
            }
            document.querySelector(
              '#outgoingTransactionForm input[name="transaction_date"]'
            ).value = data.main.transaction_date;
            document.querySelector(
              '#outgoingTransactionForm input[name="document_number"]'
            ).value = docNumber;
            document.querySelector(
              '#outgoingTransactionForm textarea[name="description"]'
            ).value = data.main.description;
            document.querySelector(
              '#outgoingTransactionForm select[name="status"]'
            ).value = data.main.status;
            outgoingItems = data.items.map((item) => ({ ...item }));
            renderItemsTable();
          })
          .catch((err) => {
            console.error("Fetch Error:", err);
            Swal.fire("Error!", "Gagal memuat detail transaksi.", "error");
          });
      } else {
        modalTitle.innerHTML =
          '<i class="bi bi-plus-circle-fill me-2"></i>Tambah Transaksi Barang Keluar';
        document.querySelector(
          '#outgoingTransactionForm input[name="transaction_date"]'
        ).value = new Date().toISOString().slice(0, 10);
      }
    });

    mainForm.addEventListener("submit", (e) => {
      if (outgoingItems.length === 0) {
        e.preventDefault();
        Swal.fire(
          "Daftar Kosong!",
          "Harap tambahkan minimal satu item.",
          "warning"
        );
        return;
      }
      hiddenJsonInput.value = JSON.stringify(outgoingItems);
    });
  }
  // --- LOGIKA UNTUK HALAMAN KARTU STOK (STOCK JALUR) ---
  const stockJalurPage = document.getElementById("stockJalurPage");
  if (stockJalurPage) {
    const productNameInput = document.getElementById("product_name_kartu_stok");
    const productDatalist = document.getElementById(
      "datalistProductsKartuStok"
    );
    const productIdHidden = document.getElementById(
      "product_id_kartu_stok_hidden"
    );
    const batchSelect = document.getElementById("incoming_id");
    const selectedBatchId = stockJalurPage.dataset.selectedBatchId;

    function fetchAndPopulateBatches(productId) {
      batchSelect.innerHTML = '<option value="">Memuat batch...</option>';
      batchSelect.disabled = true;

      if (!productId) {
        batchSelect.innerHTML =
          '<option value="">-- Pilih Nama Barang dulu --</option>';
        return;
      }

      fetch(`api_get_batches.php?product_id=${productId}`)
        .then((response) => response.json())
        .then((data) => {
          batchSelect.innerHTML =
            '<option value="" selected disabled>-- Pilih Batch --</option>';
          if (data.length > 0) {
            data.forEach((batch) => {
              const optionText = `Tgl: ${batch.transaction_date} - Batch: ${
                batch.batch_number || "N/A"
              } - Supplier: ${batch.supplier || "-"}`;
              batchSelect.innerHTML += `<option value="${batch.id}">${optionText}</option>`;
            });
            batchSelect.disabled = false;

            if (selectedBatchId) {
              batchSelect.value = selectedBatchId;
            }
          } else {
            batchSelect.innerHTML =
              '<option value="">-- Tidak ada batch tersedia --</option>';
          }
        })
        .catch((error) => {
          console.error("Error fetching batches:", error);
          batchSelect.innerHTML = '<option value="">Gagal memuat</option>';
        });
    }

    productNameInput.addEventListener("input", function () {
      const selectedOption = Array.from(productDatalist.options).find(
        (opt) => opt.value === this.value
      );
      if (selectedOption) {
        const productId = selectedOption.dataset.id;
        productIdHidden.value = productId;
        fetchAndPopulateBatches(productId);
      } else {
        productIdHidden.value = "";
        batchSelect.innerHTML =
          '<option value="">-- Pilih Nama Barang dulu --</option>';
        batchSelect.disabled = true;
      }
    });

    // Cek saat halaman dimuat
    if (productNameInput.value) {
      const selectedOption = Array.from(productDatalist.options).find(
        (opt) => opt.value === productNameInput.value
      );
      if (selectedOption) {
        const productId = selectedOption.dataset.id;
        productIdHidden.value = productId; // Pastikan hidden input terisi
        fetchAndPopulateBatches(productId);
      }
    }
  }

  const keluarkan501ModalEl = document.getElementById("keluarkan501Modal");
  if (keluarkan501ModalEl) {
    const productSelect501 = document.getElementById("product_id_501");
    const batchSelect501 = document.getElementById("batch_id_501");
    const quantityInput501 = document.getElementById("quantity_501");

    productSelect501.addEventListener("change", function () {
      const productId = this.value;
      batchSelect501.innerHTML = '<option value="">Memuat batch...</option>';
      batchSelect501.disabled = true;
      quantityInput501.value = "";

      if (!productId) {
        batchSelect501.innerHTML =
          '<option value="">-- Pilih Nama Barang di atas --</option>';
        batchSelect501.disabled = false;
        return;
      }

      fetch(`api_get_batches_501.php?product_id=${productId}`)
        .then((response) => response.json())
        .then((data) => {
          batchSelect501.innerHTML =
            '<option value="" selected disabled>-- Pilih Batch --</option>';
          if (data && data.length > 0) {
            data.forEach((batch) => {
              const sisa_501 = formatAngkaJS(batch.sisa_lot_number);
              const optionText = `Tgl: ${batch.transaction_date} - Batch: ${
                batch.batch_number || "N/A"
              } (Sisa 501: ${sisa_501} Kg)`;
              batchSelect501.innerHTML += `<option value="${batch.id}" data-sisa="${batch.sisa_lot_number}">${optionText}</option>`;
            });
          } else {
            batchSelect501.innerHTML =
              '<option value="">-- Tidak ada batch dengan sisa 501 --</option>';
          }
          batchSelect501.disabled = false;
        });
    });

    // Otomatis isi jumlah 501 dengan sisa maksimalnya saat batch dipilih
    batchSelect501.addEventListener("change", function () {
      const selectedOption = this.options[this.selectedIndex];
      if (selectedOption && selectedOption.dataset.sisa) {
        quantityInput501.value = selectedOption.dataset.sisa;
      }
    });
  }
});

/* ==========================================================================
   BT BAUTECHNIK RECHNUNGSTOOL - APPLICATION LOGIC
   ========================================================================== */

document.addEventListener("DOMContentLoaded", () => {
  
  // ==========================================================================
  // STATE MANAGEMENT
  // ==========================================================================
  
  // Default Company Profile
  const defaultProfile = {
    company: "BT Bautechnik UG",
    address: "Brunnenstraße 4",
    zip: "92334",
    city: "Berching",
    mail: "bt-bautechnik@gmx.de",
    managing: "Frau Julia Haberzettel",
    taxId: "235/224/10632",
    vatId: "",
    iban: "DE93 7215 0000 0054 9064 82",
    bic: "BYLADEM1ING",
    registry: "Amtsgericht Nürnberg",
    hrb: "46210"
  };

  // State of the current invoice form
  let state = {
    profile: { ...defaultProfile },
    client: {
      id: "",
      name: "",
      street: "",
      zip: "",
      city: "",
      country: "Deutschland",
      clientNumber: ""
    },
    invoiceNumber: "",
    invoiceDate: "",
    deliveryDate: "",
    dueDays: 14,
    discountRate: 0,
    taxMode: "standard", // standard, reverse, small, custom
    taxReasonSelectValue: "",
    taxReasonText: "",
    items: [],
    customPaymentNote: "",
    customLegalText: ""
  };

  // Historical data & Saved entities
  let savedInvoices = [];
  let savedClients = [];

  // ==========================================================================
  // HELPER FUNCTIONS
  // ==========================================================================

  // Format Date to German Standard: DD.MM.YYYY
  function formatGermanDate(dateString) {
    if (!dateString) return "__.__.____";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return "__.__.____";
    
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}.${month}.${year}`;
  }

  // Format currency: 1234.56 -> 1.234,56 €
  function formatCurrency(amount) {
    return new Intl.NumberFormat('de-DE', {
      style: 'currency',
      currency: 'EUR'
    }).format(amount);
  }

  // Calculate Due Date based on invoice date and due days
  function getDueDate(dateString, days) {
    if (!dateString) return null;
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return null;
    
    date.setDate(date.getDate() + parseInt(days || 0));
    return date;
  }

  // Generate a random ID for items
  function generateId() {
    return Math.random().toString(36).substring(2, 9);
  }

  // Set input value safely only if it differs, preventing cursor jumping
  function safeSetInputValue(id, val) {
    const el = document.getElementById(id);
    if (el && el.value !== val) {
      el.value = val;
    }
  }

  // Keep dropdown selection and custom text wrapper in sync with taxReasonText
  function syncTaxReasonSelectState() {
    const predefinedReasons = [
      "Steuerfreie innergemeinschaftliche Lieferung nach § 4 Nr. 1b i.V.m. § 6a UStG",
      "Steuerfreie Ausfuhrlieferung nach § 4 Nr. 1a i.V.m. § 6 UStG",
      "Steuerfreie Vermietung und Verpachtung nach § 4 Nr. 12 UStG",
      "Steuerfreie Heilbehandlung nach § 4 Nr. 14 UStG",
      "Umsatzsteuerfreie Leistungen nach § 4 Nr. 21 UStG (Bildungsleistungen)",
      "Steuerfreie Umsätze für die Luft- und Seeschifffahrt nach § 4 Nr. 2 UStG"
    ];
    
    const text = state.taxReasonText || "";
    
    if (state.taxReasonSelectValue === "custom") {
      return;
    }
    
    if (text === "") {
      state.taxReasonSelectValue = "";
    } else if (predefinedReasons.includes(text)) {
      state.taxReasonSelectValue = text;
    } else {
      state.taxReasonSelectValue = "custom";
    }
  }

  // Update specific item row's total label in editor directly
  function updateItemRowTotalLabel(index) {
    const item = state.items[index];
    if (!item) return;
    const qty = parseFloat(item.quantity) || 0;
    const price = parseFloat(item.price) || 0;
    const total = qty * price;
    
    const rowTotals = document.querySelectorAll(".item-row-total");
    if (rowTotals[index]) {
      rowTotals[index].textContent = `Gesamt: ${total.toFixed(2)} €`;
    }
  }

  // Generate a sequential invoice number template based on existing archived invoices in local storage
  function suggestInvoiceNumber() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const datePrefix = `RE-${yyyy}${mm}${dd}-`;

    // 1. Check if there are already invoices for today
    const todaysInvoices = savedInvoices.filter(inv => 
      inv.invoiceNumber && inv.invoiceNumber.startsWith(datePrefix)
    );

    if (todaysInvoices.length > 0) {
      let maxSeq = 0;
      todaysInvoices.forEach(inv => {
        const parts = inv.invoiceNumber.split('-');
        const seqPart = parseInt(parts[parts.length - 1]);
        if (!isNaN(seqPart) && seqPart > maxSeq) {
          maxSeq = seqPart;
        }
      });
      return `${datePrefix}${String(maxSeq + 1).padStart(2, '0')}`;
    }

    // 2. Fallback: Check if there's a simple sequential counter (like RE-10024 -> RE-10025)
    if (savedInvoices.length > 0) {
      let maxNum = 0;
      let prefix = "RE-";
      let hasSimpleCounter = false;

      savedInvoices.forEach(inv => {
        const match = inv.invoiceNumber.match(/^([A-Za-z0-9]+-)?(\d+)$/);
        if (match) {
          const num = parseInt(match[2]);
          // Make sure it's not a date-based number (which has a long digit string of 8 characters)
          if (!isNaN(num) && match[2].length < 8 && num > maxNum) {
            maxNum = num;
            prefix = match[1] || "";
            hasSimpleCounter = true;
          }
        }
      });

      if (hasSimpleCounter && maxNum > 0) {
        return `${prefix}${maxNum + 1}`;
      }
    }

    // 3. Absolute fallback (first invoice today)
    return `${datePrefix}01`;
  }

  // Generate a sequential customer number starting from KD-10000 based on existing clients
  function generateClientNumber() {
    let maxNum = 9999; // starting base for KD-10000
    savedClients.forEach(c => {
      if (c.clientNumber && c.clientNumber.startsWith("KD-")) {
        const numPart = parseInt(c.clientNumber.replace("KD-", ""));
        if (!isNaN(numPart) && numPart > maxNum) {
          maxNum = numPart;
        }
      }
    });
    return `KD-${maxNum + 1}`;
  }

  // ==========================================================================
  // CORE STORAGE OPERATORS
  // ==========================================================================

  function loadFromLocalStorage() {
    // 1. Profile load
    const localProfile = localStorage.getItem("bt_profile");
    if (localProfile) {
      state.profile = JSON.parse(localProfile);
      // Auto-correct "De93" to "DE93" if loaded from older localStorage
      if (state.profile.iban && state.profile.iban.startsWith("De")) {
        state.profile.iban = "DE" + state.profile.iban.substring(2);
        saveProfileToStorage();
      }
      // Populate defaults for HRB and taxId if they are empty
      let needsSave = false;
      if (!state.profile.hrb) {
        state.profile.hrb = "46210";
        needsSave = true;
      }
      if (!state.profile.taxId) {
        state.profile.taxId = "235/224/10632";
        needsSave = true;
      }
      if (needsSave) {
        saveProfileToStorage();
      }
    }

    // 2. Saved Invoices & Clients
    const localInvoices = localStorage.getItem("bt_invoices");
    if (localInvoices) {
      savedInvoices = JSON.parse(localInvoices);
    }
    
    const localClients = localStorage.getItem("bt_clients");
    if (localClients) {
      savedClients = JSON.parse(localClients);
    }

    // 3. Current active form state
    const localState = localStorage.getItem("bt_current_draft");
    if (localState) {
      const parsedDraft = JSON.parse(localState);
      // Merge with profile settings to ensure profile changes carry over to drafts
      state = {
        ...state,
        ...parsedDraft,
        profile: { ...state.profile } // always keep fresh profile
      };
    } else {
      // Default initial item
      state.items = [
        {
          id: generateId(),
          description: "Bauleistung / Stundenlohnarbeiten laut Leistungsbeschreibung",
          quantity: 1,
          unit: "pauschal",
          price: 1500.00,
          vatRate: 19
        }
      ];
      state.invoiceNumber = suggestInvoiceNumber();
      state.invoiceDate = new Date().toISOString().split('T')[0];
      state.deliveryDate = "Leistungsdatum entspricht Rechnungsdatum";
    }
  }

  function saveCurrentDraft() {
    localStorage.setItem("bt_current_draft", JSON.stringify({
      client: state.client,
      invoiceNumber: state.invoiceNumber,
      invoiceDate: state.invoiceDate,
      deliveryDate: state.deliveryDate,
      dueDays: state.dueDays,
      discountRate: state.discountRate,
      taxMode: state.taxMode,
      taxReasonSelectValue: state.taxReasonSelectValue,
      taxReasonText: state.taxReasonText,
      items: state.items,
      customPaymentNote: state.customPaymentNote,
      customLegalText: state.customLegalText
    }));
  }

  function saveProfileToStorage() {
    localStorage.setItem("bt_profile", JSON.stringify(state.profile));
  }

  // ==========================================================================
  // CALCULATIONS & FORM STATE SYNC
  // ==========================================================================

  function calculateInvoice() {
    let subtotal = 0;
    
    // Calculate each item's total
    state.items.forEach(item => {
      const qty = parseFloat(item.quantity) || 0;
      const price = parseFloat(item.price) || 0;
      item.total = qty * price;
      subtotal += item.total;
    });

    // Discount
    const discountRate = parseFloat(state.discountRate) || 0;
    const discountValue = subtotal * (discountRate / 100);
    const subtotalAfterDiscount = subtotal - discountValue;

    // Taxes
    let taxes = {};
    let totalTax = 0;

    if (state.taxMode === "standard") {
      state.items.forEach(item => {
        const itemQty = parseFloat(item.quantity) || 0;
        const itemPrice = parseFloat(item.price) || 0;
        const itemNet = itemQty * itemPrice;
        
        // Item-level discount application for tax calculation
        const itemNetDiscounted = itemNet - (itemNet * (discountRate / 100));
        const rate = parseFloat(item.vatRate) || 0;
        
        if (rate > 0) {
          const itemTax = itemNetDiscounted * (rate / 100);
          if (!taxes[rate]) {
            taxes[rate] = 0;
          }
          taxes[rate] += itemTax;
          totalTax += itemTax;
        }
      });
    } else {
      // Small Business (§19) and Reverse Charge (§13b) have 0% VAT
      taxes[0] = 0;
      totalTax = 0;
    }

    const grandTotal = subtotalAfterDiscount + totalTax;

    return {
      subtotal,
      discountValue,
      subtotalAfterDiscount,
      taxes,
      totalTax,
      grandTotal
    };
  }

  // Update UI and Preview sheet
  function renderAll(skipEditorRender = false) {
    // 1. Calculations
    const calc = calculateInvoice();

    // 2. Profile Sync (Inputs -> Preview & Footer)
    safeSetInputValue("profileCompany", state.profile.company);
    safeSetInputValue("profileAddress", state.profile.address);
    safeSetInputValue("profileZip", state.profile.zip);
    safeSetInputValue("profileCity", state.profile.city);
    safeSetInputValue("profileMail", state.profile.mail);
    safeSetInputValue("profileManaging", state.profile.managing);
    safeSetInputValue("profileTaxId", state.profile.taxId);
    safeSetInputValue("profileVatId", state.profile.vatId);
    safeSetInputValue("profileIban", state.profile.iban);
    safeSetInputValue("profileBic", state.profile.bic);

    // Update Live preview header & footer
    document.getElementById("viewProfileCompany").textContent = state.profile.company;
    document.getElementById("viewProfileAddress").textContent = state.profile.address;
    document.getElementById("viewProfileCity").textContent = `${state.profile.zip} ${state.profile.city}`;
    document.getElementById("viewProfileMail").textContent = state.profile.mail;
    document.getElementById("viewSenderLine").textContent = `${state.profile.company} · ${state.profile.address} · ${state.profile.zip} ${state.profile.city}`;
    
    document.getElementById("viewFooterCompany").textContent = state.profile.company;
    document.getElementById("viewFooterAddress").innerHTML = `${state.profile.address}<br>${state.profile.zip} ${state.profile.city}`;
    document.getElementById("viewFooterIban").textContent = state.profile.iban;
    document.getElementById("viewFooterBic").textContent = state.profile.bic;
    document.getElementById("viewFooterRegistry").textContent = state.profile.registry;
    document.getElementById("viewFooterManaging").textContent = state.profile.managing;
    
    document.getElementById("viewFooterRegistryNumber").textContent = state.profile.hrb 
      ? `HRB-Nummer: ${state.profile.hrb}`
      : "HRB-Nummer: Nachgereicht";
      
    document.getElementById("viewFooterTaxNumber").textContent = state.profile.taxId 
      ? `Steuernummer: ${state.profile.taxId}`
      : "Ust-Nr. Nachgereicht";
      
    if (state.profile.vatId) {
      document.getElementById("viewFooterTaxNumber").innerHTML += `<br>USt-IdNr.: ${state.profile.vatId}`;
    }

    // 3. Client Sync
    safeSetInputValue("clientName", state.client.name);
    safeSetInputValue("clientStreet", state.client.street);
    safeSetInputValue("clientZip", state.client.zip);
    safeSetInputValue("clientCity", state.client.city);
    safeSetInputValue("clientCountry", state.client.country);
    
    let clientAddressHTML = `<strong>${state.client.name || 'Musterkunde GmbH'}</strong>\n`;
    if (state.client.street) clientAddressHTML += `${state.client.street}\n`;
    if (state.client.zip || state.client.city) {
      clientAddressHTML += `${state.client.zip} ${state.client.city}\n`;
    }
    if (state.client.country && state.client.country.toLowerCase() !== "deutschland") {
      clientAddressHTML += `${state.client.country}`;
    }
    document.getElementById("viewRecipientAddress").innerHTML = clientAddressHTML;

    // 4. Metadata Sync
    safeSetInputValue("invoiceNumber", state.invoiceNumber);
    safeSetInputValue("clientNumber", state.client.clientNumber);
    safeSetInputValue("invoiceDate", state.invoiceDate);
    safeSetInputValue("deliveryDate", state.deliveryDate);
    safeSetInputValue("dueDays", state.dueDays);
    safeSetInputValue("discountRate", state.discountRate);
    safeSetInputValue("taxMode", state.taxMode);
    
    // Sync the tax reason dropdown and text state before setting values
    syncTaxReasonSelectState();
    
    safeSetInputValue("taxReasonSelect", state.taxReasonSelectValue || "");
    document.getElementById("customTaxReasonWrapper").style.display = state.taxReasonSelectValue === "custom" ? "block" : "none";
    safeSetInputValue("taxReasonText", state.taxReasonText || "");
    safeSetInputValue("customPaymentNote", state.customPaymentNote || "");
    safeSetInputValue("customLegalText", state.customLegalText || "");

    // Show/hide taxReasonGroup depending on mode or 0% items
    const hasZeroVatItem = state.items.some(item => parseFloat(item.vatRate) === 0);
    const taxReasonGroup = document.getElementById("taxReasonGroup");
    if (state.taxMode === "custom" || (state.taxMode === "standard" && hasZeroVatItem)) {
      taxReasonGroup.style.display = "block";
    } else {
      taxReasonGroup.style.display = "none";
    }

    document.getElementById("viewInvoiceNumber").textContent = state.invoiceNumber || "RE-XXXX-XXX";
    document.getElementById("viewClientNumber").textContent = state.client.clientNumber || "KD-XXXX";
    document.getElementById("viewInvoiceDate").textContent = formatGermanDate(state.invoiceDate);
    document.getElementById("viewDeliveryDate").textContent = state.deliveryDate || "Leistungsdatum entspricht Rechnungsdatum";

    // 5. Items Editor Sync & Render
    if (!skipEditorRender) {
      renderItemsEditor();
    }

    // 6. Preview Items Render
    const previewItemsContainer = document.getElementById("previewItemsContainer");
    previewItemsContainer.innerHTML = "";
    
    if (state.items.length === 0) {
      previewItemsContainer.innerHTML = `<tr><td colspan="5" style="text-align: center; color: #888;">Keine Posten hinzugefügt.</td></tr>`;
    } else {
      state.items.forEach((item, index) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td class="col-pos">${index + 1}</td>
          <td class="col-desc">
            <strong>${item.description.split('\n')[0]}</strong>
            ${item.description.split('\n').slice(1).length > 0 ? `<div style="font-size: 8pt; color: var(--paper-text-muted); margin-top: 2px;">${item.description.split('\n').slice(1).join('<br>')}</div>` : ''}
          </td>
          <td class="col-qty">${item.quantity} ${item.unit || 'Stk.'}</td>
          <td class="col-price">${formatCurrency(item.price)}</td>
          <td class="col-total">${formatCurrency(item.total)}</td>
        `;
        previewItemsContainer.appendChild(tr);
      });
    }

    // 7. Render Calculations Summary
    document.getElementById("viewSubtotal").textContent = formatCurrency(calc.subtotal);
    
    // Discount row visibility
    const viewDiscountLabel = document.getElementById("viewDiscountLabel");
    const viewDiscountValue = document.getElementById("viewDiscountValue");
    if (calc.discountValue > 0) {
      viewDiscountLabel.style.display = "block";
      viewDiscountLabel.textContent = `Rabatt (${state.discountRate}%):`;
      viewDiscountValue.style.display = "block";
      viewDiscountValue.textContent = `-${formatCurrency(calc.discountValue)}`;
    } else {
      viewDiscountLabel.style.display = "none";
      viewDiscountValue.style.display = "none";
    }

    // Tax rows visibility & dynamic creation
    const totalsSection = document.querySelector(".totals-section");
    // Remove existing tax label rows (we find them by checking classes)
    const existingTaxRows = totalsSection.querySelectorAll(".dynamic-tax-row");
    existingTaxRows.forEach(row => row.remove());

    const grandTotalRow = totalsSection.querySelector(".totals-bold");

    if (state.taxMode === "standard") {
      // Hide static tax element
      document.getElementById("viewTaxLabel").style.display = "none";
      document.getElementById("viewTaxValue").style.display = "none";

      // Append calculated VAT groups
      Object.keys(calc.taxes).forEach(rate => {
        const labelDiv = document.createElement("div");
        labelDiv.className = "totals-label dynamic-tax-row";
        labelDiv.textContent = `zzgl. ${rate}% Umsatzsteuer:`;
        
        const valDiv = document.createElement("div");
        valDiv.className = "totals-value dynamic-tax-row";
        valDiv.textContent = formatCurrency(calc.taxes[rate]);

        // Insert before grand total
        totalsSection.insertBefore(labelDiv, grandTotalRow);
        totalsSection.insertBefore(valDiv, grandTotalRow);
      });
    } else {
      // Hide standard VAT rows
      document.getElementById("viewTaxLabel").style.display = "none";
      document.getElementById("viewTaxValue").style.display = "none";
    }

    document.getElementById("viewGrandTotal").textContent = formatCurrency(calc.grandTotal);

    // 8. Legal Notice logic
    const viewLegalNotice = document.getElementById("viewLegalNotice");
    let noticeHTML = "";
    
    if (state.taxMode === "reverse") {
      noticeHTML = `<strong>Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge) nach § 13b UStG.</strong><br>Die Umsatzsteuer ist vom Leistungsempfänger anzumelden und abzuführen.`;
    } else if (state.taxMode === "small") {
      noticeHTML = `Gemäß § 19 UStG wird keine Umsatzsteuer berechnet und ausgewiesen (Kleinunternehmerregelung).`;
    } else if (state.taxMode === "custom") {
      noticeHTML = state.taxReasonText ? `<strong>${state.taxReasonText}</strong>` : `Steuerfreie Lieferung / Leistung.`;
    } else if (state.taxMode === "standard" && hasZeroVatItem) {
      noticeHTML = state.taxReasonText ? `Hinweis zu den steuerfreien Posten: <strong>${state.taxReasonText}</strong>` : `Hinweis: Enthält steuerfreie Lieferungen oder Leistungen.`;
    }
    
    if (state.customLegalText) {
      noticeHTML += noticeHTML ? `<br>${state.customLegalText}` : state.customLegalText;
    }
    viewLegalNotice.innerHTML = noticeHTML;

    // 9. Payment note rendering with due date
    const dueDate = getDueDate(state.invoiceDate, state.dueDays);
    const formattedDueDate = dueDate ? formatGermanDate(dueDate) : "[DATUM]";
    
    const paymentNoteArea = document.getElementById("viewPaymentNote");
    if (state.customPaymentNote) {
      paymentNoteArea.textContent = state.customPaymentNote.replace("[DATUM]", formattedDueDate).replace("[BETRAG]", formatCurrency(calc.grandTotal));
    } else {
      paymentNoteArea.innerHTML = `Bitte überweisen Sie den Rechnungsbetrag von <strong>${formatCurrency(calc.grandTotal)}</strong> bis zum <strong>${formattedDueDate}</strong> auf das unten genannte Bankkonto unter Angabe der Rechnungsnummer <strong>${state.invoiceNumber || 'RE-XXXX'}</strong>.`;
    }

    // Auto-save the draft
    saveCurrentDraft();
  }

  // Render the editable items in the control panel
  function renderItemsEditor() {
    const itemsContainer = document.getElementById("itemsContainer");
    itemsContainer.innerHTML = "";

    state.items.forEach((item, index) => {
      const row = document.createElement("tr");
      row.className = "items-editor-row";
      row.innerHTML = `
        <td style="vertical-align: top; text-align: center; padding-top: 10px; font-weight: bold; font-size: 0.8rem;">
          ${index + 1}
        </td>
        <td>
          <textarea class="item-desc" data-index="${index}" placeholder="Leistungsbeschreibung">${item.description}</textarea>
          <div class="form-group-triple" style="margin-top: 0.25rem;">
            <input type="number" class="item-qty" data-index="${index}" value="${item.quantity}" step="any" placeholder="Menge">
            <input type="text" class="item-unit" data-index="${index}" value="${item.unit || ''}" placeholder="Einheit (z.B. Std, m²)">
            <select class="item-vat" data-index="${index}" ${state.taxMode !== 'standard' ? 'disabled' : ''}>
              ${state.taxMode !== 'standard'
                ? `<option value="0" selected>0% USt</option>`
                : `
                  <option value="19" ${item.vatRate === 19 ? 'selected' : ''}>19% USt</option>
                  <option value="7" ${item.vatRate === 7 ? 'selected' : ''}>7% USt</option>
                  <option value="0" ${item.vatRate === 0 ? 'selected' : ''}>0% USt</option>
                `
              }
            </select>
          </div>
        </td>
        <td style="vertical-align: top;">
          <input type="number" class="item-price" data-index="${index}" value="${item.price}" step="0.01" placeholder="Preis (€)">
          <div class="item-row-total" style="font-size: 0.75rem; text-align: right; margin-top: 4px; color: var(--text-secondary);">
            Gesamt: ${(item.quantity * item.price).toFixed(2)} €
          </div>
        </td>
        <td style="vertical-align: top; text-align: center; padding-top: 4px;">
          <button class="btn btn-danger btn-sm btn-delete-item" data-index="${index}" style="padding: 0.3rem 0.5rem;" title="Posten löschen">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
          </button>
        </td>
      `;
      itemsContainer.appendChild(row);
    });

    // Item editor change listeners
    document.querySelectorAll(".item-desc").forEach(el => {
      el.addEventListener("input", (e) => {
        const idx = e.target.dataset.index;
        state.items[idx].description = e.target.value;
        renderAll(true); // skip items editor re-render to keep focus!
      });
    });

    document.querySelectorAll(".item-qty").forEach(el => {
      el.addEventListener("input", (e) => {
        const idx = e.target.dataset.index;
        state.items[idx].quantity = parseFloat(e.target.value) || 0;
        updateItemRowTotalLabel(idx);
        renderAll(true); // skip items editor re-render to keep focus!
      });
    });

    document.querySelectorAll(".item-unit").forEach(el => {
      el.addEventListener("input", (e) => {
        const idx = e.target.dataset.index;
        state.items[idx].unit = e.target.value;
        renderAll(true); // skip items editor re-render to keep focus!
      });
    });

    document.querySelectorAll(".item-vat").forEach(el => {
      el.addEventListener("change", (e) => {
        const idx = e.target.dataset.index;
        state.items[idx].vatRate = parseInt(e.target.value) || 0;
        renderAll(true); // skip items editor re-render to keep focus!
      });
    });

    document.querySelectorAll(".item-price").forEach(el => {
      el.addEventListener("input", (e) => {
        const idx = e.target.dataset.index;
        state.items[idx].price = parseFloat(e.target.value) || 0;
        updateItemRowTotalLabel(idx);
        renderAll(true); // skip items editor re-render to keep focus!
      });
    });

    document.querySelectorAll(".btn-delete-item").forEach(btn => {
      btn.addEventListener("click", (e) => {
        const idx = e.currentTarget.dataset.index;
        state.items.splice(idx, 1);
        renderAll();
      });
    });
  }

  // Populate Customer Dropdown
  function updateClientSelectDropdown() {
    const clientSelect = document.getElementById("clientSelect");
    clientSelect.innerHTML = `<option value="">-- Neuen Kunden anlegen --</option>`;
    
    savedClients.forEach(c => {
      const option = document.createElement("option");
      option.value = c.id;
      option.textContent = `${c.name} (${c.city || 'Kein Ort'})`;
      clientSelect.appendChild(option);
    });
  }

  // ==========================================================================
  // EVENT LISTENERS & FORM EVENT BINDINGS
  // ==========================================================================

  // Profile data inputs
  const profileFields = [
    { id: "profileCompany", prop: "company" },
    { id: "profileAddress", prop: "address" },
    { id: "profileZip", prop: "zip" },
    { id: "profileCity", prop: "city" },
    { id: "profileMail", prop: "mail" },
    { id: "profileManaging", prop: "managing" },
    { id: "profileTaxId", prop: "taxId" },
    { id: "profileVatId", prop: "vatId" },
    { id: "profileIban", prop: "iban" },
    { id: "profileBic", prop: "bic" }
  ];

  profileFields.forEach(field => {
    document.getElementById(field.id).addEventListener("input", (e) => {
      state.profile[field.prop] = e.target.value;
      saveProfileToStorage();
      renderAll();
    });
  });

  // Client inputs
  const clientFields = [
    { id: "clientName", prop: "name" },
    { id: "clientStreet", prop: "street" },
    { id: "clientZip", prop: "zip" },
    { id: "clientCity", prop: "city" },
    { id: "clientCountry", prop: "country" }
  ];

  clientFields.forEach(field => {
    document.getElementById(field.id).addEventListener("input", (e) => {
      state.client[field.prop] = e.target.value;
      
      // Auto-generate client number if typing the name and number is empty
      if (field.id === "clientName" && e.target.value.trim() !== "" && !state.client.clientNumber) {
        state.client.clientNumber = generateClientNumber();
      }
      
      renderAll();
    });
  });

  // Customer dropdown select
  document.getElementById("clientSelect").addEventListener("change", (e) => {
    const selectedId = e.target.value;
    if (selectedId) {
      const match = savedClients.find(c => c.id === selectedId);
      if (match) {
        state.client = { ...match };
        renderAll();
      }
    } else {
      state.client = {
        id: "",
        name: "",
        street: "",
        zip: "",
        city: "",
        country: "Deutschland",
        clientNumber: state.client.clientNumber // preserve KD-Nr
      };
      renderAll();
    }
  });

  // Invoice details inputs
  document.getElementById("invoiceNumber").addEventListener("input", (e) => {
    state.invoiceNumber = e.target.value;
    renderAll();
  });
  document.getElementById("clientNumber").addEventListener("input", (e) => {
    state.client.clientNumber = e.target.value;
    renderAll();
  });
  document.getElementById("invoiceDate").addEventListener("input", (e) => {
    state.invoiceDate = e.target.value;
    renderAll();
  });
  document.getElementById("deliveryDate").addEventListener("input", (e) => {
    state.deliveryDate = e.target.value;
    renderAll();
  });
  document.getElementById("dueDays").addEventListener("input", (e) => {
    state.dueDays = parseInt(e.target.value) || 0;
    renderAll();
  });
  document.getElementById("discountRate").addEventListener("input", (e) => {
    state.discountRate = parseFloat(e.target.value) || 0;
    renderAll();
  });
  document.getElementById("taxMode").addEventListener("change", (e) => {
    state.taxMode = e.target.value;
    renderAll();
  });
  document.getElementById("taxReasonSelect").addEventListener("change", (e) => {
    const val = e.target.value;
    state.taxReasonSelectValue = val;
    if (val === "custom") {
      state.taxReasonText = document.getElementById("taxReasonText").value;
    } else {
      state.taxReasonText = val;
    }
    renderAll();
  });
  document.getElementById("taxReasonText").addEventListener("input", (e) => {
    state.taxReasonText = e.target.value;
    renderAll();
  });
  document.getElementById("customPaymentNote").addEventListener("input", (e) => {
    state.customPaymentNote = e.target.value;
    renderAll();
  });
  document.getElementById("customLegalText").addEventListener("input", (e) => {
    state.customLegalText = e.target.value;
    renderAll();
  });

  // Add Item button
  document.getElementById("btnAddItem").addEventListener("click", () => {
    state.items.push({
      id: generateId(),
      description: "Bauleistung / Materiallieferung laut Vereinbarung",
      quantity: 1,
      unit: "Stk.",
      price: 100.00,
      vatRate: 19
    });
    renderAll();
  });

  // Reset form button
  document.getElementById("btnResetForm").addEventListener("click", () => {
    if (confirm("Sind Sie sicher, dass Sie alle Rechnungsfelder leeren möchten? Das Firmenprofil bleibt erhalten.")) {
      state.client = {
        id: "",
        name: "",
        street: "",
        zip: "",
        city: "",
        country: "Deutschland",
        clientNumber: ""
      };
      state.invoiceNumber = suggestInvoiceNumber();
      state.invoiceDate = new Date().toISOString().split('T')[0];
      state.deliveryDate = "Leistungsdatum entspricht Rechnungsdatum";
      state.dueDays = 14;
      state.discountRate = 0;
      state.taxMode = "standard";
      state.taxReasonSelectValue = "";
      state.taxReasonText = "";
      state.customPaymentNote = "";
      state.customLegalText = "";
      state.items = [
        {
          id: generateId(),
          description: "Neuer Posten",
          quantity: 1,
          unit: "pauschal",
          price: 0.00,
          vatRate: 19
        }
      ];
      document.getElementById("clientSelect").value = "";
      renderAll();
    }
  });

  // Print button
  document.getElementById("btnPrintInvoice").addEventListener("click", () => {
    // 1. If permanent client saving is toggled, save client
    if (document.getElementById("saveClientCheckbox").checked && state.client.name.trim() !== "") {
      saveOrUpdateActiveClient();
    }
    
    // 2. Trigger native browser print which respects our CSS A4 media-query
    window.print();
  });

  // Save invoice to Archive
  document.getElementById("btnSaveInvoice").addEventListener("click", () => {
    if (!state.invoiceNumber) {
      alert("Bitte geben Sie eine Rechnungsnummer ein, bevor Sie die Rechnung archivieren.");
      return;
    }

    if (document.getElementById("saveClientCheckbox").checked && state.client.name.trim() !== "") {
      saveOrUpdateActiveClient();
    }

    const calc = calculateInvoice();
    
    // Create copy of the current state for archive
    const invoiceArchiveEntry = {
      id: generateId(),
      invoiceNumber: state.invoiceNumber,
      invoiceDate: state.invoiceDate,
      clientName: state.client.name || "Unbenannter Kunde",
      taxMode: state.taxMode,
      grandTotal: calc.grandTotal,
      netTotal: calc.subtotalAfterDiscount,
      stateData: JSON.parse(JSON.stringify(state)) // deep copy
    };

    // Check if this invoice number was already saved
    const existingIdx = savedInvoices.findIndex(inv => inv.invoiceNumber === state.invoiceNumber);
    if (existingIdx !== -1) {
      if (confirm(`Eine Rechnung mit der Nummer ${state.invoiceNumber} existiert bereits im Archiv. Möchten Sie diese überschreiben?`)) {
        savedInvoices[existingIdx] = invoiceArchiveEntry;
        alert("Rechnung erfolgreich aktualisiert!");
      } else {
        return;
      }
    } else {
      savedInvoices.push(invoiceArchiveEntry);
      alert("Rechnung erfolgreich archiviert!");
    }

    localStorage.setItem("bt_invoices", JSON.stringify(savedInvoices));
    updateClientSelectDropdown();
  });

  // ==========================================================================
  // CLIENT SAVING METHOD
  // ==========================================================================

  function saveOrUpdateActiveClient() {
    if (!state.client.name.trim()) return;

    const existingIdx = savedClients.findIndex(c => 
      c.name.toLowerCase().trim() === state.client.name.toLowerCase().trim()
    );

    // If clientNumber is empty, generate it now
    if (!state.client.clientNumber) {
      state.client.clientNumber = generateClientNumber();
    }

    const clientData = {
      id: state.client.id || generateId(),
      name: state.client.name,
      street: state.client.street,
      zip: state.client.zip,
      city: state.client.city,
      country: state.client.country,
      clientNumber: state.client.clientNumber
    };

    if (existingIdx !== -1) {
      clientData.id = savedClients[existingIdx].id;
      savedClients[existingIdx] = clientData;
    } else {
      savedClients.push(clientData);
    }

    state.client.id = clientData.id;
    localStorage.setItem("bt_clients", JSON.stringify(savedClients));
    updateClientSelectDropdown();
    document.getElementById("clientSelect").value = clientData.id;
  }

  // ==========================================================================
  // ARCHIV / DASHBOARD OPERATIONS
  // ==========================================================================

  const dashboardOverlay = document.getElementById("dashboardOverlay");

  document.getElementById("btnOpenDashboard").addEventListener("click", () => {
    renderDashboard();
    dashboardOverlay.classList.add("active");
  });

  document.getElementById("btnCloseDashboard").addEventListener("click", () => {
    dashboardOverlay.classList.remove("active");
  });

  // Close dashboard on click outside modal content
  dashboardOverlay.addEventListener("click", (e) => {
    if (e.target === dashboardOverlay) {
      dashboardOverlay.classList.remove("active");
    }
  });

  function renderDashboard() {
    // 1. Stats calculation
    const count = savedInvoices.length;
    let totalNet = 0;
    savedInvoices.forEach(inv => {
      totalNet += inv.netTotal || 0;
    });
    const avg = count > 0 ? totalNet / count : 0;

    document.getElementById("statInvoiceCount").textContent = count;
    document.getElementById("statNetRevenue").textContent = formatCurrency(totalNet);
    document.getElementById("statAvgRevenue").textContent = formatCurrency(avg);

    // 2. Invoice History List
    const listBody = document.getElementById("invoiceListBody");
    listBody.innerHTML = "";

    if (savedInvoices.length === 0) {
      listBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: #888;">Keine archivierten Rechnungen vorhanden.</td></tr>`;
    } else {
      // Sort newest first
      const sortedInvoices = [...savedInvoices].sort((a, b) => new Date(b.invoiceDate) - new Date(a.invoiceDate));
      
      sortedInvoices.forEach(inv => {
        const tr = document.createElement("tr");
        let steuerLabel = "Standard";
        if (inv.taxMode === "reverse") steuerLabel = "Reverse Charge";
        if (inv.taxMode === "small") steuerLabel = "Kleinunternehmer";

        tr.innerHTML = `
          <td><strong>${inv.invoiceNumber}</strong></td>
          <td>${formatGermanDate(inv.invoiceDate)}</td>
          <td>${inv.clientName}</td>
          <td><span style="font-size: 0.75rem; background: rgba(0,86,179,0.1); color: var(--brand-accent); padding: 2px 6px; border-radius: 4px;">${steuerLabel}</span></td>
          <td style="text-align: right; font-weight: bold;">${formatCurrency(inv.grandTotal)}</td>
          <td style="text-align: center;">
            <button class="btn btn-secondary btn-sm btn-load-archive" data-id="${inv.id}" style="padding: 0.2rem 0.4rem; font-size: 0.7rem;">Laden</button>
            <button class="btn btn-danger btn-sm btn-delete-archive" data-id="${inv.id}" style="padding: 0.2rem 0.4rem; font-size: 0.7rem; margin-left: 4px;">Löschen</button>
          </td>
        `;
        listBody.appendChild(tr);
      });
    }

    // Add listeners for Load & Delete actions
    document.querySelectorAll(".btn-load-archive").forEach(btn => {
      btn.addEventListener("click", (e) => {
        const id = e.target.dataset.id;
        const match = savedInvoices.find(inv => inv.id === id);
        if (match) {
          state = JSON.parse(JSON.stringify(match.stateData));
          renderAll();
          dashboardOverlay.classList.remove("active");
          alert(`Rechnung ${match.invoiceNumber} erfolgreich geladen!`);
        }
      });
    });

    document.querySelectorAll(".btn-delete-archive").forEach(btn => {
      btn.addEventListener("click", (e) => {
        const id = e.target.dataset.id;
        const idx = savedInvoices.findIndex(inv => inv.id === id);
        if (idx !== -1) {
          if (confirm(`Möchten Sie die Rechnung ${savedInvoices[idx].invoiceNumber} wirklich aus dem Archiv löschen?`)) {
            savedInvoices.splice(idx, 1);
            localStorage.setItem("bt_invoices", JSON.stringify(savedInvoices));
            renderDashboard();
          }
        }
      });
    });

    // 3. Client List Management in Modal
    const clientListBody = document.getElementById("clientListBody");
    clientListBody.innerHTML = "";

    if (savedClients.length === 0) {
      clientListBody.innerHTML = `<tr><td colspan="3" style="text-align: center; color: #888;">Keine gespeicherten Kunden vorhanden.</td></tr>`;
    } else {
      savedClients.forEach(c => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td><strong>${c.name}</strong><br><span style="font-size: 0.75rem; color: var(--text-secondary);">KD-Nr: ${c.clientNumber || '-'}</span></td>
          <td>${c.street || ''}, ${c.zip || ''} ${c.city || ''}</td>
          <td style="text-align: center;">
            <button class="btn btn-danger btn-sm btn-delete-client" data-id="${c.id}" style="padding: 0.2rem 0.4rem; font-size: 0.7rem;">Entfernen</button>
          </td>
        `;
        clientListBody.appendChild(tr);
      });
    }

    document.querySelectorAll(".btn-delete-client").forEach(btn => {
      btn.addEventListener("click", (e) => {
        const id = e.target.dataset.id;
        const idx = savedClients.findIndex(c => c.id === id);
        if (idx !== -1) {
          if (confirm(`Möchten Sie den Kunden "${savedClients[idx].name}" wirklich löschen?`)) {
            savedClients.splice(idx, 1);
            localStorage.setItem("bt_clients", JSON.stringify(savedClients));
            updateClientSelectDropdown();
            renderDashboard();
          }
        }
      });
    });
  }

  // ==========================================================================
  // THEME MANAGEMENT (DARK / LIGHT / SYSTEM)
  // ==========================================================================
  
  const themeToggle = document.getElementById("themeToggle");
  
  function applyTheme(theme) {
    if (theme === "dark") {
      document.documentElement.setAttribute("data-theme", "dark");
      localStorage.setItem("color-scheme", "dark");
    } else if (theme === "light") {
      document.documentElement.setAttribute("data-theme", "light");
      localStorage.setItem("color-scheme", "light");
    } else {
      // System default
      localStorage.removeItem("color-scheme");
      if (window.matchMedia("(prefers-color-scheme: dark)").matches) {
        document.documentElement.setAttribute("data-theme", "dark");
      } else {
        document.documentElement.setAttribute("data-theme", "light");
      }
    }
  }

  themeToggle.addEventListener("click", () => {
    const currentTheme = document.documentElement.getAttribute("data-theme");
    const nextTheme = currentTheme === "dark" ? "light" : "dark";
    applyTheme(nextTheme);
  });

  // Listen to OS theme changes if user hasn't pinned one
  window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (e) => {
    const pinnedScheme = localStorage.getItem("color-scheme");
    if (!pinnedScheme) {
      applyTheme("system");
    }
  });

  // ==========================================================================
  // INITIALIZATION RUN
  // ==========================================================================

  // Load storage
  loadFromLocalStorage();
  
  // Setup client dropdown items
  updateClientSelectDropdown();
  
  // Render form inputs & preview
  renderAll();

  // Show logout button if auth cookie is present
  const hasAuthCookie = document.cookie.split(";").some(c => c.trim().startsWith("bt_auth_session="));
  if (hasAuthCookie) {
    const logoutBtn = document.getElementById("btnLogout");
    if (logoutBtn) logoutBtn.style.display = "inline-flex";
  }

});

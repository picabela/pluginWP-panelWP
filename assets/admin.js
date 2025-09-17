;(($) => {
  // Declare jQuery variable
  var jQuery = window.jQuery

  // Declare repairOrderAjax variable
  var repairOrderAjax = window.repairOrderAjax

  jQuery(document).ready(($) => {
    // Generate order link
    $("#generate_link").on("click", () => {
      var serviceDescription = $("#link_service_description").val()
      var servicePrice = $("#link_service_price").val()

      if (!serviceDescription || !servicePrice) {
        alert("Proszę wypełnić wszystkie pola")
        return
      }

      $.ajax({
        url: repairOrderAjax.ajaxurl,
        type: "POST",
        data: {
          action: "generate_order_link",
          service_description: serviceDescription,
          service_price: servicePrice,
          nonce: repairOrderAjax.nonce,
        },
        success: (response) => {
          if (response.success) {
            $("#generated_link").val(response.data.link)
            $("#generated_link_container").show()
          } else {
            alert("Błąd podczas generowania linku")
          }
        },
      })
    })

    // Copy link to clipboard
    $("#copy_link, .copy-link-url").on("click", function () {
      var url = $(this).data("url") || $("#generated_link").val()

      if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
          alert("Link skopiowany do schowka")
        })
      } else {
        // Fallback for older browsers
        var tempInput = $("<input>")
        $("body").append(tempInput)
        tempInput.val(url).select()
        document.execCommand("copy")
        tempInput.remove()
        alert("Link skopiowany do schowka")
      }
    })

    // Update order status
    $(".order-status").on("change", function () {
      var orderId = $(this).data("order")
      var statusType = $(this).data("type")
      var statusValue = $(this).val()

      $.ajax({
        url: repairOrderAjax.ajaxurl,
        type: "POST",
        data: {
          action: "update_order_status",
          order_id: orderId,
          status_type: statusType,
          status_value: statusValue,
          nonce: repairOrderAjax.nonce,
        },
        success: function (response) {
          if (response.success) {
            // Visual feedback
            $(this).closest("tr").addClass("updated")
            setTimeout(() => {
              $(".updated").removeClass("updated")
            }, 2000)
          }
        },
      })
    })

    // View order details
    $(".view-order").on("click", function () {
      var orderId = $(this).data("order")
      // Open order details modal or redirect to details page
      window.open("?page=repair-orders&action=view&order=" + orderId, "_blank")
    })

    // Generate dynamic link
    $("#generate_dynamic_link").on("click", () => {
      console.log("[v0] Generate link button clicked")

      var serviceDescription = $("#link_service_description").val()
      var servicePrice = $("#link_service_price").val()
      var customName = $("#link_custom_name").val()
      var defaultLocker = $("#link_default_locker").val()
      var expiryDays = $("#link_expiry_days").val()
      var usageLimit = $("#link_usage_limit").val()

      console.log("[v0] Form data:", {
        serviceDescription,
        servicePrice,
        customName,
        defaultLocker,
        expiryDays,
        usageLimit,
      })

      if (!serviceDescription || !servicePrice) {
        alert("Proszę wypełnić opis usługi i cenę")
        return
      }

      $("#generate_dynamic_link").prop("disabled", true).text("Generowanie...")

      $.ajax({
        url: repairOrderAjax.ajaxurl,
        type: "POST",
        data: {
          action: "generate_dynamic_link",
          service_description: serviceDescription,
          service_price: servicePrice,
          custom_name: customName,
          default_locker: defaultLocker,
          expiry_days: expiryDays,
          usage_limit: usageLimit,
          nonce: repairOrderAjax.nonce,
        },
        success: (response) => {
          console.log("[v0] AJAX response:", response)

          if (response.success) {
            $("#generated_link").val(response.data.link_url)
            $("#generated_link_container").show()

            alert("Link został wygenerowany pomyślnie!")

            // Refresh the page to show the new link in the table
            setTimeout(() => {
              location.reload()
            }, 1500)
          } else {
            console.error("[v0] Error response:", response)
            alert("Błąd podczas generowania linku: " + (response.data || "Nieznany błąd"))
          }
        },
        error: (xhr, status, error) => {
          console.error("[v0] AJAX error:", { xhr, status, error })
          alert("Wystąpił błąd podczas generowania linku: " + error)
        },
        complete: () => {
          $("#generate_dynamic_link").prop("disabled", false).text("Generuj Link")
        },
      })
    })

    // Test link
    $("#test_link").on("click", () => {
      var url = $("#generated_link").val()
      if (url) {
        window.open(url, "_blank")
      }
    })

    // View analytics
    $(".view-analytics").on("click", function () {
      var linkId = $(this).data("link")

      $.ajax({
        url: repairOrderAjax.ajaxurl,
        type: "POST",
        data: {
          action: "get_link_analytics",
          link_id: linkId,
          nonce: repairOrderAjax.nonce,
        },
        success: (response) => {
          if (response.success) {
            displayAnalytics(response.data)
            $("#analytics-modal").show()
          } else {
            alert("Błąd podczas pobierania analityki")
          }
        },
      })
    })

    // Delete link
    $(".delete-link").on("click", function () {
      if (!confirm("Czy na pewno chcesz usunąć ten link?")) {
        return
      }

      var linkId = $(this).data("link")
      var row = $(this).closest("tr")

      $.ajax({
        url: repairOrderAjax.ajaxurl,
        type: "POST",
        data: {
          action: "delete_generated_link",
          link_id: linkId,
          nonce: repairOrderAjax.nonce,
        },
        success: (response) => {
          if (response.success) {
            row.fadeOut(() => {
              row.remove()
            })
          } else {
            alert("Błąd podczas usuwania linku")
          }
        },
      })
    })

    // Close modals
    $(".close-modal").on("click", function () {
      $(this).closest(".link-modal, .order-modal").hide()
    })

    // Display analytics function
    function displayAnalytics(data) {
      var html = `
        <div class="analytics-summary">
          <h4>Podsumowanie</h4>
          <div class="stats-row">
            <div class="stat-item">
              <span class="stat-label">Całkowite użycia:</span>
              <span class="stat-value">${data.link_data.usage_count}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Konwersja:</span>
              <span class="stat-value">${data.conversion_rate.toFixed(1)}%</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Zamówienia:</span>
              <span class="stat-value">${data.orders_from_link.length}</span>
            </div>
          </div>
        </div>
        
        <div class="analytics-charts">
          <h4>Użycia dzienne (ostatnie 30 dni)</h4>
          <div class="daily-usage-chart">
      `

      if (data.daily_usage.length > 0) {
        data.daily_usage.forEach((day) => {
          html += `<div class="usage-bar">
            <span class="usage-date">${day.date}</span>
            <div class="usage-bar-fill" style="width: ${(day.count / Math.max(...data.daily_usage.map((d) => d.count))) * 100}%"></div>
            <span class="usage-count">${day.count}</span>
          </div>`
        })
      } else {
        html += "<p>Brak danych o użyciach</p>"
      }

      html += `</div></div>`

      if (data.referrer_stats.length > 0) {
        html += `
          <div class="referrer-stats">
            <h4>Źródła ruchu</h4>
            <ul>
        `
        data.referrer_stats.forEach((ref) => {
          html += `<li>${ref.referrer || "Bezpośredni"}: ${ref.count} użyć</li>`
        })
        html += "</ul></div>"
      }

      $("#analytics-content").html(html)
    }
  })
})(window.jQuery)

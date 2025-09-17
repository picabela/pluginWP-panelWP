;(($) => {
  var jQuery = window.jQuery
  var repairOrderAjax = window.repairOrderAjax

  jQuery(document).ready(($) => {
    // Handle form submission with improved routing
    $("#repair-order-form").on("submit", function (e) {
      e.preventDefault()

      var $form = $(this)
      var $submitBtn = $form.find(".submit-btn")
      var $btnText = $submitBtn.find(".btn-text")
      var $btnLoading = $submitBtn.find(".btn-loading")

      // Show loading state
      $submitBtn.prop("disabled", true)
      if ($btnText.length && $btnLoading.length) {
        $btnText.hide()
        $btnLoading.show()
      } else {
        $submitBtn.text("Przetwarzanie...")
      }

      var formData = $form.serialize()
      formData += "&action=submit_repair_order&nonce=" + repairOrderAjax.frontend_nonce

      var orderIdFromUrl = null
      var pathMatch = window.location.pathname.match(/\/zamowienie\/([^/]+)/)
      if (pathMatch && pathMatch[1] !== "platnosc" && pathMatch[1] !== "potwierdzenie") {
        orderIdFromUrl = pathMatch[1]
        formData += "&order_id=" + orderIdFromUrl
      }

      console.log("[v0] Submitting form with data:", formData)

      $.ajax({
        url: repairOrderAjax.ajaxurl,
        type: "POST",
        data: formData,
        success: (response) => {
          console.log("[v0] Form submission response:", response)

          if (response.success) {
            window.location.href = response.data.redirect_url
          } else {
            alert("Błąd podczas składania zamówienia: " + response.data)
            // Reset button state
            $submitBtn.prop("disabled", false)
            if ($btnText.length && $btnLoading.length) {
              $btnText.show()
              $btnLoading.hide()
            } else {
              $submitBtn.text("Złóż zamówienie i przejdź do płatności")
            }
          }
        },
        error: (xhr, status, error) => {
          console.error("[v0] Form submission error:", { xhr, status, error })
          alert("Wystąpił błąd podczas składania zamówienia")
          // Reset button state
          $submitBtn.prop("disabled", false)
          if ($btnText.length && $btnLoading.length) {
            $btnText.show()
            $btnLoading.hide()
          } else {
            $submitBtn.text("Złóż zamówienie i przejdź do płatności")
          }
        },
      })
    })

    // Handle payment simulation with improved feedback
    $("#simulate-payment").on("click", function () {
      var orderId = $(this).data("order")
      var $btn = $(this)
      var $btnText = $btn.find(".btn-text")
      var $btnLoading = $btn.find(".btn-loading")

      console.log("[v0] Starting payment simulation for order:", orderId)

      $btn.prop("disabled", true)
      if ($btnText.length && $btnLoading.length) {
        $btnText.hide()
        $btnLoading.show()
      } else {
        $btn.text("Przetwarzanie płatności...")
      }

      $.ajax({
        url: repairOrderAjax.ajaxurl,
        type: "POST",
        data: {
          action: "simulate_payment",
          order_id: orderId,
          nonce: repairOrderAjax.frontend_nonce,
        },
        success: (response) => {
          console.log("[v0] Payment simulation response:", response)

          if (response.success) {
            alert("Płatność została zrealizowana pomyślnie!")
            setTimeout(() => {
              location.reload()
            }, 1000)
          } else {
            alert("Błąd podczas przetwarzania płatności: " + response.data)
            // Reset button state
            $btn.prop("disabled", false)
            if ($btnText.length && $btnLoading.length) {
              $btnText.show()
              $btnLoading.hide()
            } else {
              $btn.text("Zapłać teraz (Symulacja)")
            }
          }
        },
        error: (xhr, status, error) => {
          console.error("[v0] Payment simulation error:", { xhr, status, error })
          alert("Wystąpił błąd podczas przetwarzania płatności")
          // Reset button state
          $btn.prop("disabled", false)
          if ($btnText.length && $btnLoading.length) {
            $btnText.show()
            $btnLoading.hide()
          } else {
            $btn.text("Zapłać teraz (Symulacja)")
          }
        },
      })
    })

    // Handle shipment generation with improved feedback
    $("#generate-shipment").on("click", function () {
      var orderId = $(this).data("order")
      var $btn = $(this)
      var $btnText = $btn.find(".btn-text")
      var $btnLoading = $btn.find(".btn-loading")

      console.log("[v0] Starting shipment generation for order:", orderId)

      $btn.prop("disabled", true)
      if ($btnText.length && $btnLoading.length) {
        $btnText.hide()
        $btnLoading.show()
      } else {
        $btn.text("Generowanie etykiety...")
      }

      $.ajax({
        url: repairOrderAjax.ajaxurl,
        type: "POST",
        data: {
          action: "generate_shipment",
          order_id: orderId,
          nonce: repairOrderAjax.frontend_nonce,
        },
        success: (response) => {
          console.log("[v0] Shipment generation response:", response)

          if (response.success) {
            alert("Etykieta została wygenerowana pomyślnie!")
            setTimeout(() => {
              location.reload()
            }, 1000)
          } else {
            alert("Błąd podczas generowania etykiety: " + response.data)
            // Reset button state
            $btn.prop("disabled", false)
            if ($btnText.length && $btnLoading.length) {
              $btnText.show()
              $btnLoading.hide()
            } else {
              $btn.text("Wygeneruj etykietę wysyłkową")
            }
          }
        },
        error: (xhr, status, error) => {
          console.error("[v0] Shipment generation error:", { xhr, status, error })
          alert("Wystąpił błąd podczas generowania etykiety")
          // Reset button state
          $btn.prop("disabled", false)
          if ($btnText.length && $btnLoading.length) {
            $btnText.show()
            $btnLoading.hide()
          } else {
            $btn.text("Wygeneruj etykietę wysyłkową")
          }
        },
      })
    })

    // Check if we're on an estimate page and pre-fill form
    var urlParams = new URLSearchParams(window.location.search)
    var estimateId = urlParams.get("IDzamowienia")

    if (estimateId) {
      console.log("[v0] Detected estimate ID from URL:", estimateId)
      // The form should already be pre-filled by PHP, but we can add additional JS handling here if needed
    }

    // Monitor URL changes for single-page app behavior
    var currentPath = window.location.pathname
    if (currentPath.includes("/zamowienie/")) {
      console.log("[v0] On order page, path:", currentPath)

      // Extract order ID from path
      var pathParts = currentPath.split("/")
      var orderIndex = pathParts.indexOf("zamowienie")
      if (orderIndex !== -1 && pathParts[orderIndex + 1]) {
        var pageType = pathParts[orderIndex + 1]
        console.log("[v0] Order page type:", pageType)

        // Handle different page types
        if (pageType === "platnosc") {
          console.log("[v0] On payment page")
        } else if (pageType === "potwierdzenie") {
          console.log("[v0] On confirmation page")
        } else {
          console.log("[v0] On order details page for:", pageType)
        }
      }
    }
  })
})(window.jQuery)

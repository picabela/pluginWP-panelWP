"use client"

import { useEffect, useState } from "react"
import { useSearchParams } from "next/navigation"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { CheckCircle, AlertCircle, Package, Download, ExternalLink } from "lucide-react"

interface ShipmentData {
  id: string
  tracking_number?: string
}

export default function PaymentReturn() {
  const searchParams = useSearchParams()
  const [status, setStatus] = useState<"loading" | "success" | "error">("loading")
  const [shipmentData, setShipmentData] = useState<ShipmentData | null>(null)
  const [error, setError] = useState<string>("")

  const createShipment = async () => {
    // Get form data and config from localStorage (stored before payment)
    const formDataStr = localStorage.getItem("repairFormData")
    const formConfigStr = localStorage.getItem("currentFormConfig")
    const globalSettingsStr = localStorage.getItem("currentGlobalSettings")
    
    if (!formDataStr) {
      throw new Error("Brak danych formularza")
    }
    
    if (!formConfigStr || !globalSettingsStr) {
      throw new Error("Brak konfiguracji")
    }
    
    const formData = JSON.parse(formDataStr)
    const formConfig = JSON.parse(formConfigStr)
    const globalSettings = JSON.parse(globalSettingsStr)
    
    const API_BASE_URL = globalSettings.inpost.mode === "production" 
      ? "https://api-shipx-pl.easypack24.net/v1"
      : "https://sandbox-api-shipx-pl.easypack24.net/v1"
    
    const payload = {
      receiver: {
        name: globalSettings.inpost.recipientName,
        email: globalSettings.inpost.recipientEmail,
        phone: globalSettings.inpost.recipientPhone,
        company_name: globalSettings.inpost.recipientName,
      },
      sender: {
        name: `${formData.firstName} ${formData.lastName}`,
        email: formData.email,
        phone: formData.phone,
        company_name: `${formData.firstName} ${formData.lastName}`,
      },
      parcels: [
        {
          template: "small",
        },
      ],
      service: "inpost_locker_standard",
      custom_attributes: {
        target_point: globalSettings.inpost.targetPaczkomat,
        sending_method: "parcel_locker",
      },
    }

    const response = await fetch(`${API_BASE_URL}/organizations/${globalSettings.inpost.organizationId}/shipments`, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${globalSettings.inpost.apiToken}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    })

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(errorData.message || "Błąd podczas tworzenia przesyłki")
    }

    return await response.json()
  }

  const getShipmentDetails = async (shipmentId: string) => {
    const globalSettingsStr = localStorage.getItem("currentGlobalSettings")
    if (!globalSettingsStr) throw new Error("Brak konfiguracji")
    
    const globalSettings = JSON.parse(globalSettingsStr)
    const API_BASE_URL = globalSettings.inpost.mode === "production" 
      ? "https://api-shipx-pl.easypack24.net/v1"
      : "https://sandbox-api-shipx-pl.easypack24.net/v1"
    
    const response = await fetch(`${API_BASE_URL}/shipments/${shipmentId}`, {
      headers: {
        Authorization: `Bearer ${globalSettings.inpost.apiToken}`,
      },
    })

    if (!response.ok) {
      throw new Error("Błąd podczas pobierania szczegółów przesyłki")
    }

    return await response.json()
  }

  const downloadLabel = async (shipmentId: string) => {
    try {
      const globalSettingsStr = localStorage.getItem("currentGlobalSettings")
      if (!globalSettingsStr) throw new Error("Brak konfiguracji")
      
      const globalSettings = JSON.parse(globalSettingsStr)
      const API_BASE_URL = globalSettings.inpost.mode === "production" 
        ? "https://api-shipx-pl.easypack24.net/v1"
        : "https://sandbox-api-shipx-pl.easypack24.net/v1"
      
      const response = await fetch(`${API_BASE_URL}/shipments/${shipmentId}/label`, {
        headers: {
          Authorization: `Bearer ${globalSettings.inpost.apiToken}`,
        },
      })

      if (!response.ok) {
        throw new Error("Błąd podczas pobierania etykiety")
      }

      const blob = await response.blob()
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement("a")
      a.href = url
      a.download = `etykieta-${shipmentId}.pdf`
      document.body.appendChild(a)
      a.click()
      window.URL.revokeObjectURL(url)
      document.body.removeChild(a)
    } catch (error) {
      console.error("Error downloading label:", error)
      alert("Błąd podczas pobierania etykiety")
    }
  }

  const pollForTrackingNumber = (shipmentId: string) => {
    const interval = setInterval(async () => {
      try {
        const details = await getShipmentDetails(shipmentId)
        if (details.tracking_number) {
          setShipmentData((prev) => (prev ? { ...prev, tracking_number: details.tracking_number } : null))
          clearInterval(interval)
        }
      } catch (error) {
        console.error("Error polling for tracking number:", error)
        clearInterval(interval)
      }
    }, 3000)

    // Stop polling after 2 minutes
    setTimeout(() => clearInterval(interval), 120000)
  }

  useEffect(() => {
    const processPaymentReturn = async () => {
      try {
        if (typeof window === 'undefined') return
        
        // Check payment status from URL parameters
        const paymentStatus = searchParams.get("status")
        const sessionId = searchParams.get("sessionId")
        
        if (paymentStatus === "success" || sessionId) {
          // Payment successful, create shipment
          const shipment = await createShipment()
          setShipmentData(shipment)
          setStatus("success")
          
          // Start polling for tracking number
          pollForTrackingNumber(shipment.id)
          
          // Clear form data from localStorage
          localStorage.removeItem("repairFormData")
          localStorage.removeItem("currentFormConfig")
          localStorage.removeItem("currentGlobalSettings")
        } else {
          // Payment failed or cancelled
          setError("Płatność została anulowana lub wystąpił błąd")
          setStatus("error")
        }
      } catch (error) {
        console.error("Error processing payment return:", error)
        setError(error instanceof Error ? error.message : "Wystąpił błąd podczas przetwarzania")
        setStatus("error")
      }
    }

    processPaymentReturn()
  }, [searchParams])

  const goHome = () => {
    if (typeof window !== 'undefined') {
      window.location.href = "/"
    }
  }

  if (status === "loading") {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-4">
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <div className="mx-auto mb-4 w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center">
              <Package className="w-8 h-8 text-amber-600" />
            </div>
            <CardTitle className="text-amber-900">Przetwarzanie zamówienia</CardTitle>
            <CardDescription>Płatność została zrealizowana, generujemy etykietę nadawczą...</CardDescription>
          </CardHeader>
          <CardContent className="text-center">
            <div className="animate-spin mx-auto w-8 h-8 border-4 border-amber-200 border-t-amber-600 rounded-full mb-4"></div>
            <p className="text-sm text-muted-foreground">Tworzenie przesyłki InPost</p>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (status === "success") {
    const formDataStr = typeof window !== 'undefined' ? localStorage.getItem("repairFormData") : null
    const formData = formDataStr ? JSON.parse(formDataStr) : {}
    
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-4">
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <div className="mx-auto mb-4 w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
              <CheckCircle className="w-8 h-8 text-green-600" />
            </div>
            <CardTitle className="text-green-600">Zamówienie zrealizowane!</CardTitle>
            <CardDescription>Płatność została przyjęta i przesyłka została utworzona</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="bg-amber-50 border border-amber-200 p-4 rounded-lg">
              <h4 className="font-medium mb-2 text-amber-900">Szczegóły przesyłki:</h4>
              <p className="text-sm text-amber-800">ID: {shipmentData?.id}</p>
              {shipmentData?.tracking_number ? (
                <div className="mt-2">
                  <p className="text-sm font-medium text-amber-900">Numer śledzenia:</p>
                  <a
                    href={`https://inpost.pl/sledzenie-przesylek?number=${shipmentData.tracking_number}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-amber-700 hover:text-amber-900 hover:underline flex items-center gap-1 text-sm"
                  >
                    {shipmentData.tracking_number}
                    <ExternalLink className="w-3 h-3" />
                  </a>
                </div>
              ) : (
                <p className="text-sm text-amber-700 mt-2">Numer śledzenia będzie dostępny za kilka minut...</p>
              )}
            </div>

            <div className="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 p-4 rounded-lg">
              <div className="flex items-start gap-3">
                <AlertCircle className="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" />
                <div>
                  <h4 className="font-medium text-amber-900 mb-1">Instrukcje wysyłki:</h4>
                  <ol className="text-sm text-amber-800 space-y-1 list-decimal list-inside">
                    <li>Pobierz i wydrukuj etykietę nadawczą</li>
                    <li>Naklej etykietę na paczkę z obuwiem do naprawy</li>
                    <li>Nadaj paczkę w dowolnym punkcie InPost</li>
                    <li>Naprawione obuwie otrzymasz w paczkomacie: <strong>{formData.returnPaczkomat}</strong></li>
                  </ol>
                </div>
              </div>
            </div>

            <Button 
              onClick={() => shipmentData && downloadLabel(shipmentData.id)} 
              className="w-full bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700"
            >
              <Download className="w-4 h-4 mr-2" />
              Pobierz etykietę nadawczą (PDF)
            </Button>

            <Button onClick={goHome} className="w-full" variant="outline">
              Złóż kolejne zamówienie
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (status === "error") {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-4">
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <div className="mx-auto mb-4 w-16 h-16 bg-destructive/10 rounded-full flex items-center justify-center">
              <AlertCircle className="w-8 h-8 text-destructive" />
            </div>
            <CardTitle className="text-destructive">Wystąpił błąd</CardTitle>
            <CardDescription>{error}</CardDescription>
          </CardHeader>
          <CardContent>
            <Button onClick={goHome} className="w-full">
              Powrót do formularza
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  return null
}
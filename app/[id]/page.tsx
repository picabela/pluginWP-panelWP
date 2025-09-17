"use client"

import { useState, useEffect } from "react"
import { useParams } from "next/navigation"
import CryptoJS from 'crypto-js'
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"
import { CheckCircle, Package, CreditCard, Download, ExternalLink, Wrench, Clock, Shield, AlertCircle } from "lucide-react"

interface FormConfig {
  id: string
  name: string
  serviceName: string
  serviceDescription: string
  servicePrice: number
  isActive: boolean
}

interface GlobalSettings {
  przelewy24: {
    merchantId: string
    posId: string
    crcKey: string
    apiKey: string
    mode: "sandbox" | "production"
  }
  
  inpost: {
    apiToken: string
    organizationId: string
    mode: "sandbox" | "production"
    recipientName: string
    recipientEmail: string
    recipientPhone: string
    targetPaczkomat: string
  }
  
  formDefaults: {
    title: string
    subtitle: string
    buttonText: string
    successMessage: string
    requirePhone: boolean
    requireDescription: boolean
  }
  phone: string
  description: string
  returnPaczkomat: string
}

interface ShipmentData {
  id: string
  tracking_number?: string
}

type FormStep = "form" | "payment" | "success" | "error"

export default function DynamicForm() {
  const params = useParams()
  const formId = params.id as string
  
  const [formConfig, setFormConfig] = useState<FormConfig | null>(null)
  const [globalSettings, setGlobalSettings] = useState<GlobalSettings | null>(null)
  const [currentStep, setCurrentStep] = useState<FormStep>("form")
  const [formData, setFormData] = useState<FormData>({
    firstName: "",
    lastName: "",
    email: "",
    phone: "",
    description: "",
    returnPaczkomat: "",
  })
  const [shipmentData, setShipmentData] = useState<ShipmentData | null>(null)
  const [error, setError] = useState<string>("")
  const [isProcessing, setIsProcessing] = useState(false)

  // Load form configuration and global settings from localStorage
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const savedForms = localStorage.getItem("repairForms")
      if (savedForms) {
        try {
          const forms: FormConfig[] = JSON.parse(savedForms)
          const form = forms.find(f => f.id === formId)
          if (form && form.isActive) {
            setFormConfig(form)
          }
        } catch (error) {
          console.error('Error parsing forms from localStorage:', error)
        }
      }

      const savedSettings = localStorage.getItem("globalSettings")
      if (savedSettings) {
        try {
          const settings: GlobalSettings = JSON.parse(savedSettings)
          setGlobalSettings(settings)
        } catch (error) {
          console.error('Error parsing global settings from localStorage:', error)
        }
      }
    }
  }, [formId])

  const handleInputChange = (field: keyof FormData, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }))
  }

  const simulatePayment = async () => {
    // Simulate payment processing delay
    await new Promise(resolve => setTimeout(resolve, 2000))
    
    // Store form data and settings for shipment creation
    if (typeof window !== 'undefined') {
      localStorage.setItem("repairFormData", JSON.stringify(formData))
      localStorage.setItem("currentFormConfig", JSON.stringify(formConfig))
      localStorage.setItem("currentGlobalSettings", JSON.stringify(globalSettings))
    }
    
    return { success: true }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    if (!formConfig || !globalSettings) {
      setError("Błąd konfiguracji formularza")
      return
    }

    // Validate required fields
    if (!formData.firstName || !formData.lastName || !formData.email || !formData.returnPaczkomat) {
      setError("Proszę wypełnić wszystkie wymagane pola")
      return
    }

    if (globalSettings.formDefaults.requirePhone && !formData.phone) {
      setError("Telefon jest wymagany")
      return
    }

    if (globalSettings.formDefaults.requireDescription && !formData.description) {
      setError("Opis problemu jest wymagany")
      return
    }

    setIsProcessing(true)
    setError("")

    try {
      // Simulate payment instead of real payment processing
      const paymentResult = await simulatePayment()
      
      if (paymentResult.success) {
        // Redirect to payment return page to simulate successful payment
        window.location.href = `/payment-return?status=success&sessionId=SIM_${Date.now()}`
      } else {
        throw new Error("Błąd symulacji płatności")
      }
    } catch (error) {
      setError(error instanceof Error ? error.message : "Wystąpił błąd podczas przetwarzania zamówienia")
      setIsProcessing(false)
    }
  }

  if (!formConfig || !globalSettings) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-4">
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <div className="mx-auto mb-4 w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
              <AlertCircle className="w-8 h-8 text-red-600" />
            </div>
            <CardTitle className="text-red-600">Formularz niedostępny</CardTitle>
            <CardDescription>
              Formularz o ID "{formId}" nie istnieje, został dezaktywowany lub brak konfiguracji.
            </CardDescription>
          </CardHeader>
        </Card>
      </div>
    )
  }

  if (currentStep === "payment") {
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

  if (currentStep === "success") {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-4">
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <div className="mx-auto mb-4 w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
              <CheckCircle className="w-8 h-8 text-green-600" />
            </div>
            <CardTitle className="text-green-600">{formConfig.formSettings.successMessage}</CardTitle>
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
                    <li>Naklej etykietę na paczkę z przedmiotem do naprawy</li>
                    <li>Nadaj paczkę w dowolnym punkcie InPost</li>
                    <li>Naprawiony przedmiot otrzymasz w paczkomacie: <strong>{formData.returnPaczkomat}</strong></li>
                  </ol>
                </div>
              </div>
            </div>

            <Button 
              onClick={() => shipmentData && window.open(`/api/download-label/${shipmentData.id}`, "_blank")} 
              className="w-full bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700"
            >
              <Download className="w-4 h-4 mr-2" />
              Pobierz etykietę nadawczą (PDF)
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (currentStep === "error") {
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
            <Button onClick={() => setCurrentStep("form")} className="w-full">
              Spróbuj ponownie
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b bg-card">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Wrench className="w-6 h-6 text-amber-600" />
              <h1 className="text-xl font-bold text-foreground">{globalSettings.inpost.recipientName}</h1>
            </div>
          </div>
        </div>
      </header>

      <div className="container mx-auto px-4 py-8">
        <div className="max-w-2xl mx-auto">
          {/* Hero Section */}
          <div className="text-center mb-8">
            <h2 className="text-3xl font-bold text-foreground mb-4 text-balance">
              {globalSettings.formDefaults.title}
            </h2>
            <p className="text-muted-foreground text-lg text-pretty">
              {globalSettings.formDefaults.subtitle}
            </p>
          </div>

          {/* Trust Indicators */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div className="flex items-center gap-3 p-4 bg-card rounded-lg border border-amber-200">
              <Shield className="w-8 h-8 text-amber-600" />
              <div>
                <h3 className="font-medium text-sm text-amber-900">Gwarancja jakości</h3>
                <p className="text-xs text-amber-700">6 miesięcy gwarancji</p>
              </div>
            </div>
            <div className="flex items-center gap-3 p-4 bg-card rounded-lg border border-amber-200">
              <Clock className="w-8 h-8 text-amber-600" />
              <div>
                <h3 className="font-medium text-sm text-amber-900">Szybka realizacja</h3>
                <p className="text-xs text-amber-700">3-5 dni roboczych</p>
              </div>
            </div>
            <div className="flex items-center gap-3 p-4 bg-card rounded-lg border border-amber-200">
              <Package className="w-8 h-8 text-amber-600" />
              <div>
                <h3 className="font-medium text-sm text-amber-900">Wygodna wysyłka</h3>
                <p className="text-xs text-amber-700">Paczkomaty InPost</p>
              </div>
            </div>
          </div>

          {/* Order Form */}
          <Card className="border-amber-200">
            <CardHeader>
              <CardTitle className="text-amber-900">Formularz zamówienia</CardTitle>
              <CardDescription>Wypełnij formularz, aby zamówić usługę naprawczą</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleSubmit} className="space-y-6">
                {/* Customer Information */}
                <div className="space-y-4">
                  <h3 className="text-lg font-medium text-amber-900">Dane kontaktowe</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="firstName">Imię *</Label>
                      <Input
                        id="firstName"
                        value={formData.firstName}
                        onChange={(e) => handleInputChange("firstName", e.target.value)}
                        placeholder="Wprowadź imię"
                        required
                        className="border-amber-200 focus:border-amber-500"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="lastName">Nazwisko *</Label>
                      <Input
                        id="lastName"
                        value={formData.lastName}
                        onChange={(e) => handleInputChange("lastName", e.target.value)}
                        placeholder="Wprowadź nazwisko"
                        required
                        className="border-amber-200 focus:border-amber-500"
                      />
                    </div>
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="email">Email *</Label>
                      <Input
                        id="email"
                        type="email"
                        value={formData.email}
                        onChange={(e) => handleInputChange("email", e.target.value)}
                        placeholder="twoj@email.pl"
                        required
                        className="border-amber-200 focus:border-amber-500"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="phone">
                        Telefon {globalSettings.formDefaults.requirePhone && "*"}
                      </Label>
                      <Input
                        id="phone"
                        type="tel"
                        value={formData.phone}
                        onChange={(e) => handleInputChange("phone", e.target.value)}
                        placeholder="+48 123 456 789"
                        required={globalSettings.formDefaults.requirePhone}
                        className="border-amber-200 focus:border-amber-500"
                      />
                    </div>
                  </div>
                </div>

                <Separator className="bg-amber-200" />

                {/* Service Information */}
                <div className="space-y-4">
                  <h3 className="text-lg font-medium text-amber-900">Informacje o usłudze</h3>
                  <div className="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-lg p-4">
                    <div className="flex items-center justify-between">
                      <div>
                        <h4 className="font-medium text-amber-900">{formConfig.serviceName}</h4>
                        <p className="text-sm text-amber-700">{formConfig.serviceDescription}</p>
                      </div>
                      <Badge className="bg-amber-100 text-amber-800 border-amber-300 hover:bg-amber-200">
                        {formConfig.servicePrice} zł brutto
                      </Badge>
                    </div>
                  </div>
                  {globalSettings.formDefaults.requireDescription && (
                    <div className="space-y-2">
                      <Label htmlFor="description">Opis problemu *</Label>
                      <Textarea
                        id="description"
                        value={formData.description}
                        onChange={(e) => handleInputChange("description", e.target.value)}
                        placeholder="Opisz szczegółowo problem..."
                        rows={3}
                        required
                        className="border-amber-200 focus:border-amber-500"
                      />
                    </div>
                  )}
                </div>

                <Separator className="bg-amber-200" />

                {/* Shipping Information */}
                <div className="space-y-4">
                  <h3 className="text-lg font-medium text-amber-900">Wysyłka zwrotna</h3>
                  <div className="space-y-2">
                    <Label htmlFor="returnPaczkomat">Paczkomat odbioru *</Label>
                    <Input
                      id="returnPaczkomat"
                      value={formData.returnPaczkomat}
                      onChange={(e) => handleInputChange("returnPaczkomat", e.target.value)}
                      placeholder="np. KRA010"
                      required
                      className="border-amber-200 focus:border-amber-500"
                    />
                    <p className="text-xs text-muted-foreground">
                      Podaj kod Paczkomatu, do którego chcesz otrzymać naprawiony przedmiot
                    </p>
                  </div>
                </div>

                {/* Order Summary */}
                <Separator className="bg-amber-200" />
                <div className="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 p-6 rounded-lg">
                  <h3 className="font-medium mb-4 text-amber-900">Podsumowanie zamówienia</h3>
                  <div className="flex justify-between items-center mb-2">
                    <span className="text-amber-800">{formConfig.serviceName}</span>
                    <span className="font-semibold text-lg text-amber-900">{formConfig.servicePrice} zł brutto</span>
                  </div>
                  <div className="text-xs text-amber-700 mt-3 p-3 bg-amber-100 rounded border border-amber-200">
                    <strong>Uwaga:</strong> Cena może ulec zmianie po ocenie przedmiotu przez naszego specjalistę
                  </div>
                </div>

                {error && (
                  <div className="bg-destructive/10 border border-destructive/20 text-destructive px-4 py-3 rounded-lg">
                    {error}
                  </div>
                )}

                <Button 
                  type="submit" 
                  className="w-full bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700 text-white font-semibold" 
                  size="lg"
                  disabled={isProcessing}
                >
                  {isProcessing ? (
                    <>
                      <div className="animate-spin w-4 h-4 mr-2 border-2 border-white/20 border-t-white rounded-full"></div>
                      Przekierowanie do płatności...
                    </>
                  ) : (
                    <>
                      <CreditCard className="w-4 h-4 mr-2" />
                      {globalSettings.formDefaults.buttonText} {formConfig.servicePrice} zł - SYMULACJA
                    </>
                  )}
                </Button>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Footer */}
      <footer className="border-t bg-card mt-16">
        <div className="container mx-auto px-4 py-8">
          <div className="text-center text-sm text-muted-foreground">
            <p>© 2024 {globalSettings.inpost.recipientName}. Wszystkie prawa zastrzeżone.</p>
          </div>
        </div>
      </footer>
    </div>
  )
}
"use client"

import { useState, useEffect } from "react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Badge } from "@/components/ui/badge"
import { Separator } from "@/components/ui/separator"
import { 
  Settings, 
  Plus, 
  Edit, 
  Trash2, 
  Copy, 
  Eye, 
  Save,
  AlertCircle,
  CheckCircle,
  CreditCard,
  Package,
  Globe,
  TestTube,
  Cog
} from "lucide-react"

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
}

interface FormConfig {
  id: string
  name: string
  serviceName: string
  serviceDescription: string
  servicePrice: number
  isActive: boolean
  createdAt: string
  updatedAt: string
}

const defaultGlobalSettings: GlobalSettings = {
  przelewy24: {
    merchantId: "2f3a3d13",
    posId: "2f3a3d13",
    crcKey: "44d745ef276a93e3",
    apiKey: "404d485c25c140efee92436763a3f0e5",
    mode: "sandbox"
  },
  
  inpost: {
    apiToken: "eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJkVzROZW9TeXk0OHpCOHg4emdZX2t5dFNiWHY3blZ0eFVGVFpzWV9TUFA4In0.eyJleHAiOjIwNTc5NTk5MTcsImlhdCI6MTc0MjU5OTkxNywianRpIjoiOGZjMWZiNmQtNTJkOS00ZDNkLTkxZWQtNTA1YTU3MGNmODA3IiwiaXNzIjoiaHR0cHM6Ly9zYW5kYm94LWxvZ2luLmlucG9zdC5wbC9hdXRoL3JlYWxtcy9leHRlcm5hbCIsInN1YiI6ImY6N2ZiZjQxYmEtYTEzZC00MGQzLTk1ZjYtOThhMmIxYmFlNjdiOjR2UlJaSDZHNW1LUWdTa2FQXy0yWFVKd19pT0hvTUIwMDF1aGE4SE5aVzAiLCJ0eXAiOiJCZWFyZXIiLCJhenAiOiJzaGlweCIsInNlc3Npb25fc3RhdGUiOiI0NzI1NjUwMi04NjIzLTQ2YmUtOTRmOC02NTA0YzZmNjk2MTMiLCJzY29wZSI6Im9wZW5pZCBhcGk6YXBpcG9pbnRzIGFwaTpzaGlweCIsInNpZCI6IjQ3MjU2NTAyLTg2MjMtNDZiZS05NGY4LTY1MDRjNmY2OTYxMyIsImFsbG93ZWRfcmVmZXJyZXJzIjoiIiwidXVpZCI6IjMxNzAwYmU3LTA2ZTAtNGVkZC05NTA1LTAzZjJhZjQ3M2QwMiIsImVtYWlsIjoia29udGFrdEBwaWNhYmVsYS5wbCJ9.gi0k1iTptAMC0iAILF9hfU5QsM3xClD59XcAs4Dax7FfGmoQTBlnsirBRO6bdVsAEaAN7eXB6kVzIc2om5bFocK8Xtk_z5ih9Piu-PmLKFp9FABmO1KUbq6ZprKBgZvHGEv01IIAgUvqKWfs_PldlCwwj9pBSjgp5IlGHiO0_xRX0kQiAd6RfIWLYuUi_zjTVltv1jS0eJ_eVmA2TOzxb2UF7mZrEpsIcoWbi_yba9g2GgJ46VxrRDI998TgBENPpMLFOECoG_-y60PF2nSU9Bl92qu0e6knxs_DxNYk_dScM0KKT842MorbniHGXcN-V8AfZzgvV1pxDLeqpb1IGA",
    organizationId: "5134",
    mode: "sandbox",
    recipientName: "Serwis Napraw",
    recipientEmail: "kontakt@picabela.pl",
    recipientPhone: "+48123456789",
    targetPaczkomat: "KRA010"
  },
  
  formDefaults: {
    title: "Zamów naprawę swojego obuwia",
    subtitle: "Profesjonalne usługi naprawcze z wygodną wysyłką przez Paczkomaty InPost",
    buttonText: "Opłać zamówienie",
    successMessage: "Zamówienie zostało zrealizowane pomyślnie!",
    requirePhone: true,
    requireDescription: false
  }
}

const defaultFormConfig: Omit<FormConfig, "id" | "createdAt" | "updatedAt"> = {
  name: "",
  serviceName: "Naprawa obuwia",
  serviceDescription: "Profesjonalna naprawa wszystkich rodzajów obuwia",
  servicePrice: 99,
  isActive: true
}

export default function AdminPanel() {
  const [forms, setForms] = useState<FormConfig[]>([])
  const [globalSettings, setGlobalSettings] = useState<GlobalSettings>(defaultGlobalSettings)
  const [editingForm, setEditingForm] = useState<FormConfig | null>(null)
  const [isCreating, setIsCreating] = useState(false)
  const [activeTab, setActiveTab] = useState<"list" | "edit" | "settings">("list")
  const [message, setMessage] = useState<{ type: "success" | "error", text: string } | null>(null)

  // Check authentication
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const isLoggedIn = localStorage.getItem("isLoggedIn")
      if (!isLoggedIn) {
        window.location.href = "/"
        return
      }
    }
  }, [])

  const logout = () => {
    if (typeof window !== 'undefined') {
      localStorage.removeItem("isLoggedIn")
      window.location.href = "/"
    }
  }

  // Load forms and settings from localStorage on component mount
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const savedForms = localStorage.getItem("repairForms")
      if (savedForms) {
        try {
          const parsedForms = JSON.parse(savedForms)
          setForms(parsedForms)
          console.log('Loaded forms from localStorage:', parsedForms)
        } catch (error) {
          console.error('Error parsing forms from localStorage:', error)
          setForms([])
        }
      }

      const savedSettings = localStorage.getItem("globalSettings")
      if (savedSettings) {
        try {
          const parsedSettings = JSON.parse(savedSettings)
          setGlobalSettings(parsedSettings)
          console.log('Loaded global settings from localStorage:', parsedSettings)
        } catch (error) {
          console.error('Error parsing global settings from localStorage:', error)
          setGlobalSettings(defaultGlobalSettings)
        }
      }
    }
  }, [])

  // Save forms to localStorage
  const saveForms = (newForms: FormConfig[]) => {
    setForms(newForms)
    if (typeof window !== 'undefined') {
      localStorage.setItem("repairForms", JSON.stringify(newForms))
      console.log('Saved forms to localStorage:', newForms)
    }
  }

  // Save global settings to localStorage
  const saveGlobalSettings = (newSettings: GlobalSettings) => {
    setGlobalSettings(newSettings)
    if (typeof window !== 'undefined') {
      localStorage.setItem("globalSettings", JSON.stringify(newSettings))
      console.log('Saved global settings to localStorage:', newSettings)
    }
  }

  const generateId = () => {
    return "form_" + Date.now()
  }

  const createForm = () => {
    const newForm: FormConfig = {
      ...defaultFormConfig,
      id: "", // Will be set by user
      name: "Nowy formularz",
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString()
    }
    setEditingForm(newForm)
    setIsCreating(true)
    setActiveTab("edit")
  }

  const editForm = (form: FormConfig) => {
    setEditingForm({ ...form })
    setIsCreating(false)
    setActiveTab("edit")
  }

  const saveForm = () => {
    if (!editingForm) return

    // Validate required fields
    if (!editingForm.id.trim()) {
      setMessage({ type: "error", text: "ID formularza jest wymagane!" })
      setTimeout(() => setMessage(null), 3000)
      return
    }

    if (!editingForm.name.trim()) {
      setMessage({ type: "error", text: "Nazwa formularza jest wymagana!" })
      setTimeout(() => setMessage(null), 3000)
      return
    }

    // Check if ID already exists (only for new forms or when ID changed)
    const existingForm = forms.find(f => f.id === editingForm.id && f.id !== (isCreating ? "" : editingForm.id))
    if (existingForm) {
      setMessage({ type: "error", text: "Formularz o tym ID już istnieje!" })
      setTimeout(() => setMessage(null), 3000)
      return
    }

    const updatedForm = {
      ...editingForm,
      updatedAt: new Date().toISOString()
    }

    let newForms: FormConfig[]
    if (isCreating) {
      newForms = [...forms, updatedForm]
    } else {
      newForms = forms.map(f => f.id === editingForm.id ? updatedForm : f)
    }

    saveForms(newForms)
    setMessage({ type: "success", text: "Formularz został zapisany pomyślnie!" })
    setActiveTab("list")
    setEditingForm(null)
    setIsCreating(false)

    setTimeout(() => setMessage(null), 3000)
  }

  const saveSettings = () => {
    saveGlobalSettings(globalSettings)
    setMessage({ type: "success", text: "Ustawienia zostały zapisane pomyślnie!" })
    setTimeout(() => setMessage(null), 3000)
  }

  const deleteForm = (id: string) => {
    if (confirm("Czy na pewno chcesz usunąć ten formularz?")) {
      const newForms = forms.filter(f => f.id !== id)
      saveForms(newForms)
      setMessage({ type: "success", text: "Formularz został usunięty." })
      setTimeout(() => setMessage(null), 3000)
    }
  }

  const toggleFormStatus = (id: string) => {
    const newForms = forms.map(f => 
      f.id === id ? { ...f, isActive: !f.isActive, updatedAt: new Date().toISOString() } : f
    )
    saveForms(newForms)
  }

  const copyFormUrl = (id: string) => {
    if (typeof window !== 'undefined') {
      const url = `${window.location.origin}/${id}`
      navigator.clipboard.writeText(url).then(() => {
        setMessage({ type: "success", text: "Link skopiowany do schowka!" })
        setTimeout(() => setMessage(null), 3000)
      }).catch(() => {
        setMessage({ type: "error", text: "Błąd podczas kopiowania linku" })
        setTimeout(() => setMessage(null), 3000)
      })
    }
  }

  const previewForm = (id: string) => {
    if (typeof window !== 'undefined') {
      window.open(`/${id}`, "_blank")
    }
  }

  const updateEditingForm = (path: string, value: any) => {
    if (!editingForm) return

    const keys = path.split(".")
    const newForm = { ...editingForm }
    let current: any = newForm

    for (let i = 0; i < keys.length - 1; i++) {
      current = current[keys[i]]
    }
    current[keys[keys.length - 1]] = value

    setEditingForm(newForm)
  }

  const updateGlobalSettings = (path: string, value: any) => {
    const keys = path.split(".")
    const newSettings = { ...globalSettings }
    let current: any = newSettings

    for (let i = 0; i < keys.length - 1; i++) {
      current = current[keys[i]]
    }
    current[keys[keys.length - 1]] = value

    setGlobalSettings(newSettings)
  }

  if (activeTab === "settings") {
    return (
      <div className="min-h-screen bg-background p-6">
        <div className="max-w-4xl mx-auto">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h1 className="text-2xl font-bold text-foreground">Ustawienia globalne</h1>
              <p className="text-muted-foreground">
                Skonfiguruj domyślne ustawienia dla wszystkich formularzy
              </p>
            </div>
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => setActiveTab("list")}>
                Powrót do listy
              </Button>
              <Button onClick={saveSettings} className="bg-amber-600 hover:bg-amber-700">
                <Save className="w-4 h-4 mr-2" />
                Zapisz ustawienia
              </Button>
            </div>
          </div>

          {message && (
            <div className={`mb-4 p-4 rounded-lg border ${
              message.type === "success" 
                ? "bg-green-50 border-green-200 text-green-800" 
                : "bg-red-50 border-red-200 text-red-800"
            }`}>
              <div className="flex items-center gap-2">
                {message.type === "success" ? <CheckCircle className="w-4 h-4" /> : <AlertCircle className="w-4 h-4" />}
                {message.text}
              </div>
            </div>
          )}

          <div className="grid gap-6">
            {/* Przelewy24 Configuration */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <CreditCard className="w-5 h-5" />
                  Konfiguracja Przelewy24 (WYŁĄCZONE - SYMULACJA)
                </CardTitle>
                <CardDescription>
                  Płatności są obecnie wyłączone. System używa symulacji płatności.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4 opacity-50">
                <div>
                  <Label htmlFor="p24Mode">Tryb</Label>
                  <Select
                    value={globalSettings.przelewy24.mode}
                    onValueChange={(value: "sandbox" | "production") => updateGlobalSettings("przelewy24.mode", value)}
                    disabled
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="sandbox">
                        <div className="flex items-center gap-2">
                          <TestTube className="w-4 h-4" />
                          Sandbox (testowy)
                        </div>
                      </SelectItem>
                      <SelectItem value="production">
                        <div className="flex items-center gap-2">
                          <Globe className="w-4 h-4" />
                          Produkcja
                        </div>
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="merchantId">Merchant ID</Label>
                    <Input
                      id="merchantId"
                      value={globalSettings.przelewy24.merchantId}
                      onChange={(e) => updateGlobalSettings("przelewy24.merchantId", e.target.value)}
                      disabled
                    />
                  </div>
                  <div>
                    <Label htmlFor="posId">POS ID</Label>
                    <Input
                      id="posId"
                      value={globalSettings.przelewy24.posId}
                      onChange={(e) => updateGlobalSettings("przelewy24.posId", e.target.value)}
                      disabled
                    />
                  </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="crcKey">CRC Key</Label>
                    <Input
                      id="crcKey"
                      type="password"
                      value={globalSettings.przelewy24.crcKey}
                      onChange={(e) => updateGlobalSettings("przelewy24.crcKey", e.target.value)}
                      disabled
                    />
                  </div>
                  <div>
                    <Label htmlFor="apiKey">API Key</Label>
                    <Input
                      id="apiKey"
                      type="password"
                      value={globalSettings.przelewy24.apiKey}
                      onChange={(e) => updateGlobalSettings("przelewy24.apiKey", e.target.value)}
                      disabled
                    />
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* InPost Configuration */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Package className="w-5 h-5" />
                  Konfiguracja InPost
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <Label htmlFor="inpostMode">Tryb</Label>
                  <Select
                    value={globalSettings.inpost.mode}
                    onValueChange={(value: "sandbox" | "production") => updateGlobalSettings("inpost.mode", value)}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="sandbox">
                        <div className="flex items-center gap-2">
                          <TestTube className="w-4 h-4" />
                          Sandbox (testowy)
                        </div>
                      </SelectItem>
                      <SelectItem value="production">
                        <div className="flex items-center gap-2">
                          <Globe className="w-4 h-4" />
                          Produkcja
                        </div>
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="inpostApiToken">API Token</Label>
                    <Textarea
                      id="inpostApiToken"
                      value={globalSettings.inpost.apiToken}
                      onChange={(e) => updateGlobalSettings("inpost.apiToken", e.target.value)}
                      rows={3}
                      className="font-mono text-xs"
                    />
                  </div>
                  <div>
                    <Label htmlFor="organizationId">Organization ID</Label>
                    <Input
                      id="organizationId"
                      value={globalSettings.inpost.organizationId}
                      onChange={(e) => updateGlobalSettings("inpost.organizationId", e.target.value)}
                    />
                  </div>
                </div>
                <Separator />
                <h4 className="font-medium">Dane odbiorcy (serwis)</h4>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <Label htmlFor="recipientName">Nazwa odbiorcy</Label>
                    <Input
                      id="recipientName"
                      value={globalSettings.inpost.recipientName}
                      onChange={(e) => updateGlobalSettings("inpost.recipientName", e.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="recipientEmail">Email odbiorcy</Label>
                    <Input
                      id="recipientEmail"
                      type="email"
                      value={globalSettings.inpost.recipientEmail}
                      onChange={(e) => updateGlobalSettings("inpost.recipientEmail", e.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="recipientPhone">Telefon odbiorcy</Label>
                    <Input
                      id="recipientPhone"
                      value={globalSettings.inpost.recipientPhone}
                      onChange={(e) => updateGlobalSettings("inpost.recipientPhone", e.target.value)}
                    />
                  </div>
                </div>
                <div>
                  <Label htmlFor="targetPaczkomat">Paczkomat docelowy (serwis)</Label>
                  <Input
                    id="targetPaczkomat"
                    value={globalSettings.inpost.targetPaczkomat}
                    onChange={(e) => updateGlobalSettings("inpost.targetPaczkomat", e.target.value)}
                    placeholder="np. KRA010"
                  />
                </div>
              </CardContent>
            </Card>

            {/* Form Defaults */}
            <Card>
              <CardHeader>
                <CardTitle>Domyślne ustawienia formularzy</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="formTitle">Domyślny tytuł formularza</Label>
                    <Input
                      id="formTitle"
                      value={globalSettings.formDefaults.title}
                      onChange={(e) => updateGlobalSettings("formDefaults.title", e.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="buttonText">Domyślny tekst przycisku</Label>
                    <Input
                      id="buttonText"
                      value={globalSettings.formDefaults.buttonText}
                      onChange={(e) => updateGlobalSettings("formDefaults.buttonText", e.target.value)}
                    />
                  </div>
                </div>
                <div>
                  <Label htmlFor="formSubtitle">Domyślny podtytuł formularza</Label>
                  <Textarea
                    id="formSubtitle"
                    value={globalSettings.formDefaults.subtitle}
                    onChange={(e) => updateGlobalSettings("formDefaults.subtitle", e.target.value)}
                    rows={2}
                  />
                </div>
                <div>
                  <Label htmlFor="successMessage">Domyślny komunikat sukcesu</Label>
                  <Input
                    id="successMessage"
                    value={globalSettings.formDefaults.successMessage}
                    onChange={(e) => updateGlobalSettings("formDefaults.successMessage", e.target.value)}
                  />
                </div>
                <div className="flex gap-4">
                  <div className="flex items-center space-x-2">
                    <input
                      type="checkbox"
                      id="requirePhone"
                      checked={globalSettings.formDefaults.requirePhone}
                      onChange={(e) => updateGlobalSettings("formDefaults.requirePhone", e.target.checked)}
                      className="rounded"
                    />
                    <Label htmlFor="requirePhone">Domyślnie wymagaj telefon</Label>
                  </div>
                  <div className="flex items-center space-x-2">
                    <input
                      type="checkbox"
                      id="requireDescription"
                      checked={globalSettings.formDefaults.requireDescription}
                      onChange={(e) => updateGlobalSettings("formDefaults.requireDescription", e.target.checked)}
                      className="rounded"
                    />
                    <Label htmlFor="requireDescription">Domyślnie wymagaj opis problemu</Label>
                  </div>
                </div>
              </CardContent>
            </Card>

            <Button onClick={logout} variant="outline" className="text-red-600 border-red-300 hover:bg-red-50">
              Wyloguj
            </Button>
          </div>
        </div>
      </div>
    )
  }

  if (activeTab === "edit" && editingForm) {
    return (
      <div className="min-h-screen bg-background p-6">
        <div className="max-w-4xl mx-auto">
          <div className="flex items-center justify-between mb-6">
            <div>
              <h1 className="text-2xl font-bold text-foreground">
                {isCreating ? "Tworzenie nowego formularza" : "Edycja formularza"}
              </h1>
              <p className="text-muted-foreground">
                Skonfiguruj parametry formularza wysyłkowego
              </p>
            </div>
            <div className="flex gap-2">
              <Button variant="outline" onClick={() => setActiveTab("list")}>
                Anuluj
              </Button>
              <Button onClick={saveForm} className="bg-amber-600 hover:bg-amber-700">
                <Save className="w-4 h-4 mr-2" />
                Zapisz formularz
              </Button>
            </div>
          </div>

          {message && (
            <div className={`mb-4 p-4 rounded-lg border ${
              message.type === "success" 
                ? "bg-green-50 border-green-200 text-green-800" 
                : "bg-red-50 border-red-200 text-red-800"
            }`}>
              <div className="flex items-center gap-2">
                {message.type === "success" ? <CheckCircle className="w-4 h-4" /> : <AlertCircle className="w-4 h-4" />}
                {message.text}
              </div>
            </div>
          )}

          <div className="grid gap-6">
            {/* Basic Settings */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Settings className="w-5 h-5" />
                  Podstawowe ustawienia
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div>
                  <Label htmlFor="formId">ID formularza *</Label>
                  <Input
                    id="formId"
                    value={editingForm.id}
                    onChange={(e) => updateEditingForm("id", e.target.value.replace(/[^a-zA-Z0-9-_]/g, ''))}
                    placeholder="np. naprawa-obuwia-2024"
                    required
                    className="font-mono"
                  />
                  <p className="text-xs text-muted-foreground mt-1">
                    Tylko litery, cyfry, myślniki i podkreślenia. Link: {typeof window !== 'undefined' ? window.location.origin : 'https://your-domain.com'}/{editingForm.id || 'ID'}
                  </p>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="name">Nazwa formularza</Label>
                    <Input
                      id="name"
                      value={editingForm.name}
                      onChange={(e) => updateEditingForm("name", e.target.value)}
                      placeholder="np. Naprawa obuwia - promocja"
                    />
                  </div>
                  <div>
                    <Label htmlFor="serviceName">Nazwa usługi</Label>
                    <Input
                      id="serviceName"
                      value={editingForm.serviceName}
                      onChange={(e) => updateEditingForm("serviceName", e.target.value)}
                      placeholder="np. Naprawa obuwia"
                    />
                  </div>
                </div>
                <div>
                  <Label htmlFor="serviceDescription">Opis usługi</Label>
                  <Textarea
                    id="serviceDescription"
                    value={editingForm.serviceDescription}
                    onChange={(e) => updateEditingForm("serviceDescription", e.target.value)}
                    placeholder="Szczegółowy opis usługi..."
                    rows={3}
                  />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="servicePrice">Cena usługi (zł brutto)</Label>
                    <Input
                      id="servicePrice"
                      type="number"
                      step="0.01"
                      min="0"
                      value={editingForm.servicePrice}
                      onChange={(e) => updateEditingForm("servicePrice", parseFloat(e.target.value) || 0)}
                    />
                  </div>
                  <div className="flex items-center space-x-2 pt-6">
                    <input
                      type="checkbox"
                      id="isActive"
                      checked={editingForm.isActive}
                      onChange={(e) => updateEditingForm("isActive", e.target.checked)}
                      className="rounded"
                    />
                    <Label htmlFor="isActive">Formularz aktywny</Label>
                  </div>
                </div>
              </CardContent>
            </Card>

            <div className="bg-amber-50 border border-amber-200 p-4 rounded-lg">
              <div className="flex items-center gap-2 mb-2">
                <AlertCircle className="w-5 h-5 text-amber-600" />
                <h4 className="font-medium text-amber-900">Informacja o ustawieniach</h4>
              </div>
              <p className="text-sm text-amber-800">
                Ustawienia płatności, InPost i domyślne ustawienia formularzy zostały przeniesione do zakładki "Ustawienia". 
                Każdy formularz będzie używał globalnych ustawień skonfigurowanych w tej zakładce.
              </p>
            </div>

            <Button onClick={logout} variant="outline" className="text-red-600 border-red-300 hover:bg-red-50">
              Wyloguj
            </Button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-background p-6">
      <div className="max-w-6xl mx-auto">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-3xl font-bold text-foreground">Panel zarządzania formularzami</h1>
            <p className="text-muted-foreground">
              Zarządzaj formularzami wysyłkowymi i ich konfiguracją
            </p>
          </div>
          <div className="flex gap-2">
            <Button onClick={() => setActiveTab("settings")} variant="outline" className="bg-amber-50 border-amber-300 text-amber-700 hover:bg-amber-100">
              <Cog className="w-4 h-4 mr-2" />
              Ustawienia
            </Button>
            <Button onClick={createForm} className="bg-amber-600 hover:bg-amber-700">
              <Plus className="w-4 h-4 mr-2" />
              Nowy formularz
            </Button>
          </div>
        </div>

        {message && (
          <div className={`mb-4 p-4 rounded-lg border ${
            message.type === "success" 
              ? "bg-green-50 border-green-200 text-green-800" 
              : "bg-red-50 border-red-200 text-red-800"
          }`}>
            <div className="flex items-center gap-2">
              {message.type === "success" ? <CheckCircle className="w-4 h-4" /> : <AlertCircle className="w-4 h-4" />}
              {message.text}
            </div>
          </div>
        )}

        {forms.length === 0 ? (
          <Card>
            <CardContent className="text-center py-12">
              <Settings className="w-12 h-12 text-muted-foreground mx-auto mb-4" />
              <h3 className="text-lg font-medium mb-2">Brak formularzy</h3>
              <p className="text-muted-foreground mb-4">
                Utwórz pierwszy formularz wysyłkowy, aby rozpocząć
              </p>
              <div className="flex gap-2 justify-center">
                <Button onClick={() => setActiveTab("settings")} variant="outline" className="bg-amber-50 border-amber-300 text-amber-700 hover:bg-amber-100">
                  <Cog className="w-4 h-4 mr-2" />
                  Skonfiguruj ustawienia
                </Button>
                <Button onClick={createForm} className="bg-amber-600 hover:bg-amber-700">
                  <Plus className="w-4 h-4 mr-2" />
                  Utwórz formularz
                </Button>
              </div>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4">
            {forms.map((form) => (
              <Card key={form.id} className="border-amber-200">
                <CardContent className="p-6">
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <div className="flex items-center gap-3 mb-2">
                        <h3 className="text-lg font-semibold text-amber-900">{form.name}</h3>
                        <Badge variant={form.isActive ? "default" : "secondary"} className={
                          form.isActive 
                            ? "bg-green-100 text-green-800 border-green-300" 
                            : "bg-gray-100 text-gray-600 border-gray-300"
                        }>
                          {form.isActive ? "Aktywny" : "Nieaktywny"}
                        </Badge>
                        <Badge className="bg-amber-100 text-amber-800 border-amber-300">
                          {form.servicePrice} zł
                        </Badge>
                      </div>
                      <p className="text-muted-foreground mb-2">{form.serviceName}</p>
                      <div className="flex items-center gap-4 text-sm text-muted-foreground">
                        <span>ID: {form.id}</span>
                        <span>Utworzono: {new Date(form.createdAt).toLocaleDateString("pl-PL")}</span>
                        <span>Zaktualizowano: {new Date(form.updatedAt).toLocaleDateString("pl-PL")}</span>
                      </div>
                      <div className="mt-2 text-xs text-muted-foreground">
                        <span className="font-mono bg-gray-100 px-2 py-1 rounded">
                          {typeof window !== 'undefined' ? window.location.origin : 'https://your-domain.com'}/{form.id}
                        </span>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => previewForm(form.id)}
                        className="text-amber-700 border-amber-300 hover:bg-amber-50"
                      >
                        <Eye className="w-4 h-4" />
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => copyFormUrl(form.id)}
                        className="text-amber-700 border-amber-300 hover:bg-amber-50"
                      >
                        <Copy className="w-4 h-4" />
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => editForm(form)}
                        className="text-amber-700 border-amber-300 hover:bg-amber-50"
                      >
                        <Edit className="w-4 h-4" />
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => toggleFormStatus(form.id)}
                        className={form.isActive 
                          ? "text-gray-600 border-gray-300 hover:bg-gray-50" 
                          : "text-green-600 border-green-300 hover:bg-green-50"
                        }
                      >
                        {form.isActive ? "Dezaktywuj" : "Aktywuj"}
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => deleteForm(form.id)}
                        className="text-red-600 border-red-300 hover:bg-red-50"
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
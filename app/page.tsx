"use client"

import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { AlertCircle, Lock, User } from "lucide-react"

export default function LoginPage() {
  const [credentials, setCredentials] = useState({
    login: "",
    password: ""
  })
  const [error, setError] = useState("")
  const [isLoading, setIsLoading] = useState(false)

  const handleLogin = (e: React.FormEvent) => {
    e.preventDefault()
    setIsLoading(true)
    setError("")

    // Simple authentication check
    if (credentials.login === "admin" && credentials.password === "qwerty") {
      // Store login status in localStorage
      if (typeof window !== 'undefined') {
        localStorage.setItem("isLoggedIn", "true")
        window.location.href = "/panelowo"
      }
    } else {
      setError("Nieprawidłowy login lub hasło")
    }
    
    setIsLoading(false)
  }

  const handleInputChange = (field: "login" | "password", value: string) => {
    setCredentials(prev => ({ ...prev, [field]: value }))
  }

  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-4">
      <Card className="w-full max-w-md">
        <CardHeader className="text-center">
          <div className="mx-auto mb-4 w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center">
            <Lock className="w-8 h-8 text-amber-600" />
          </div>
          <CardTitle className="text-amber-900">Panel Zarządzania</CardTitle>
          <CardDescription>
            Zaloguj się, aby uzyskać dostęp do panelu zarządzania formularzami
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleLogin} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="login">Login</Label>
              <div className="relative">
                <User className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <Input
                  id="login"
                  type="text"
                  value={credentials.login}
                  onChange={(e) => handleInputChange("login", e.target.value)}
                  placeholder="Wprowadź login"
                  className="pl-10 border-amber-200 focus:border-amber-500"
                  required
                />
              </div>
            </div>
            
            <div className="space-y-2">
              <Label htmlFor="password">Hasło</Label>
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <Input
                  id="password"
                  type="password"
                  value={credentials.password}
                  onChange={(e) => handleInputChange("password", e.target.value)}
                  placeholder="Wprowadź hasło"
                  className="pl-10 border-amber-200 focus:border-amber-500"
                  required
                />
              </div>
            </div>

            {error && (
              <div className="bg-destructive/10 border border-destructive/20 text-destructive px-4 py-3 rounded-lg flex items-center gap-2">
                <AlertCircle className="w-4 h-4" />
                {error}
              </div>
            )}

            <Button 
              type="submit" 
              className="w-full bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700"
              disabled={isLoading}
            >
              {isLoading ? "Logowanie..." : "Zaloguj się"}
            </Button>
          </form>

          <div className="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <h4 className="font-medium text-amber-900 mb-2">Dane testowe:</h4>
            <p className="text-sm text-amber-800">Login: <code className="bg-amber-100 px-1 rounded">admin</code></p>
            <p className="text-sm text-amber-800">Hasło: <code className="bg-amber-100 px-1 rounded">qwerty</code></p>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
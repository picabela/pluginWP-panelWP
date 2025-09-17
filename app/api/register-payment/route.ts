import { NextRequest, NextResponse } from 'next/server'
import CryptoJS from 'crypto-js'

// Generate MD5 hash
function generateMD5(text: string): string {
  return CryptoJS.MD5(text).toString()
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json()
    
    // Get form configuration if formId is provided
    let config = null
    if (body.formId) {
      // In a real application, you would fetch this from a database
      // For now, we'll use a placeholder or default config
      config = {
        MERCHANT_ID: body.merchantId?.toString(16) || "2f3a3d13",
        POS_ID: body.posId?.toString(16) || "2f3a3d13",
        CRC_KEY: body.crcKey || "44d745ef276a93e3",
        API_KEY: body.apiKey || "404d485c25c140efee92436763a3f0e5",
        SANDBOX_URL: body.mode === 'production' ? "https://secure.przelewy24.pl" : "https://sandbox.przelewy24.pl"
      }
    } else {
      // Default configuration
      config = {
        MERCHANT_ID: "2f3a3d13",
        POS_ID: "2f3a3d13",
        CRC_KEY: "44d745ef276a93e3",
        API_KEY: "404d485c25c140efee92436763a3f0e5",
        SANDBOX_URL: "https://sandbox.przelewy24.pl"
      }
    }
    
    console.log('Received payment registration request:', {
      sessionId: body.sessionId,
      amount: body.amount,
      email: body.email,
      description: body.description
    })
    
    // Validate required fields
    const requiredFields = ['sessionId', 'amount', 'email', 'description']
    for (const field of requiredFields) {
      if (!body[field]) {
        return NextResponse.json(
          { error: `Missing required field: ${field}` },
          { status: 400 }
        )
      }
    }

    // Prepare payment registration data
    const paymentData = {
      merchantId: parseInt(config.MERCHANT_ID, 16),
      posId: parseInt(config.POS_ID, 16),
      sessionId: body.sessionId,
      amount: body.amount,
      currency: "PLN",
      description: body.description,
      email: body.email,
      country: "PL",
      language: "pl",
      urlReturn: body.urlReturn || `${request.nextUrl.origin}/payment-return`,
      urlStatus: body.urlStatus || `${request.nextUrl.origin}/api/payment-status`,
      sign: body.sign,
      encoding: "UTF-8"
    }

    console.log('Sending to Przelewy24:', {
      url: `${config.SANDBOX_URL}/api/v1/transaction/register`,
      paymentData: {
        ...paymentData,
        // Don't log the full authorization header for security
        authHeader: 'Basic [HIDDEN]'
      }
    })

    // Register transaction with Przelewy24
    const response = await fetch(`${config.SANDBOX_URL}/api/v1/transaction/register`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Basic ${Buffer.from(`${parseInt(config.POS_ID, 16)}:${config.API_KEY}`).toString('base64')}`
      },
      body: JSON.stringify(paymentData)
    })

    if (!response.ok) {
      const errorText = await response.text()
      console.error('Przelewy24 registration error:', {
        status: response.status,
        statusText: response.statusText,
        errorText,
        headers: Object.fromEntries(response.headers.entries())
      })
      return NextResponse.json(
        { error: 'Payment registration failed', details: errorText, status: response.status },
        { status: response.status }
      )
    }

    const result = await response.json()
    console.log('Przelewy24 registration success:', result)
    
    return NextResponse.json({
      token: result.data?.token,
      sessionId: body.sessionId,
      success: true
    })

  } catch (error) {
    console.error('Payment registration error:', error)
    return NextResponse.json(
      { error: 'Internal server error', details: error instanceof Error ? error.message : 'Unknown error' },
      { status: 500 }
    )
  }
}
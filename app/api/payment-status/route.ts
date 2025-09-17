import { NextRequest, NextResponse } from 'next/server'
import CryptoJS from 'crypto-js'

const PRZELEWY24_CONFIG = {
  MERCHANT_ID: "2f3a3d13",
  POS_ID: "2f3a3d13", 
  CRC_KEY: "44d745ef276a93e3",
  API_KEY: "404d485c25c140efee92436763a3f0e5",
  SANDBOX_URL: "https://sandbox.przelewy24.pl"
}

// Generate MD5 hash
function generateMD5(text: string): string {
  return CryptoJS.MD5(text).toString()
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json()
    
    // Log the notification for debugging
    console.log('Payment status notification received:', body)
    
    // Verify the signature
    const signString = `${body.sessionId}|${body.orderId}|${body.amount}|${body.currency}|${PRZELEWY24_CONFIG.CRC_KEY}`
    const expectedSign = generateMD5(signString)
    
    console.log('Payment status verification:', {
      receivedSign: body.sign,
      expectedSign,
      signString,
      sessionId: body.sessionId,
      orderId: body.orderId
    })
    
    if (body.sign !== expectedSign) {
      console.error('Invalid signature in payment notification')
      return NextResponse.json({ error: 'Invalid signature' }, { status: 400 })
    }

    // Verify transaction with Przelewy24
    const verifyData = {
      merchantId: parseInt(PRZELEWY24_CONFIG.MERCHANT_ID, 16),
      posId: parseInt(PRZELEWY24_CONFIG.POS_ID, 16),
      sessionId: body.sessionId,
      amount: body.amount,
      currency: body.currency,
      orderId: body.orderId
    }

    const verifyResponse = await fetch(`${PRZELEWY24_CONFIG.SANDBOX_URL}/api/v1/transaction/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Basic ${Buffer.from(`${parseInt(PRZELEWY24_CONFIG.POS_ID, 16)}:${PRZELEWY24_CONFIG.API_KEY}`).toString('base64')}`
      },
      body: JSON.stringify(verifyData)
    })

    if (verifyResponse.ok) {
      // Payment verified successfully
      console.log('Payment verified successfully for session:', body.sessionId)
      
      // Here you would typically:
      // 1. Update order status in database
      // 2. Send confirmation email
      // 3. Trigger shipment creation
      
      return NextResponse.json({ status: 'OK' })
    } else {
      console.error('Payment verification failed')
      return NextResponse.json({ error: 'Verification failed' }, { status: 400 })
    }

  } catch (error) {
    console.error('Payment status processing error:', error)
    return NextResponse.json({ error: 'Internal server error' }, { status: 500 })
  }
}
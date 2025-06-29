@extends('emails.layout')

@section('content')
    <h2 style="color: #e9ecef; margin-bottom: 20px;">Verifica il tuo indirizzo email</h2>
    
    <p style="color: #e9ecef; margin-bottom: 15px;">Ciao {{ $user->name }},</p>
    
    <p style="color: #e9ecef; margin-bottom: 15px;">
        Grazie per esserti registrato su UnlimitedDB! Per completare la registrazione e accedere a tutte le funzionalità del sito, 
        devi verificare il tuo indirizzo email.
    </p>
    
    <p style="color: #e9ecef; margin-bottom: 20px;">
        Clicca sul pulsante qui sotto per verificare il tuo account:
    </p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $verificationUrl }}" 
           style="display: inline-block; padding: 12px 30px; background-color: #28a745; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
            Verifica Email
        </a>
    </div>
    
    <p style="color: #e9ecef; margin-bottom: 15px;">
        Se il pulsante non funziona, puoi copiare e incollare questo link nel tuo browser:
    </p>
    
    <p style="color: #6c757d; word-break: break-all; background: #495057; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
        {{ $verificationUrl }}
    </p>
    
    <p style="color: #e9ecef; margin-top: 20px; font-size: 14px;">
        <strong>Nota:</strong> Questo link di verifica è valido e deve essere utilizzato per attivare il tuo account. 
        Se non hai richiesto questa registrazione, puoi ignorare questa email.
    </p>
@endsection

@php
    $hideDefaultFooter = true;
    $buttonText = 'Vai al sito';
@endphp

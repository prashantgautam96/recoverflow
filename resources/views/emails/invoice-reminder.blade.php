<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Hello {{ $client?->name ?? 'there' }},</p>

    @if (! empty($reminder->body))
        <p>{{ $reminder->body }}</p>
    @else
        <p>This is a reminder that invoice {{ $invoice?->invoice_number }} is due.</p>
    @endif

    @if ($invoice !== null)
        <p>
            <strong>Invoice:</strong> {{ $invoice->invoice_number }}<br>
            <strong>Amount:</strong> {{ number_format($invoice->amount_cents / 100, 2) }} {{ $invoice->currency }}<br>
            <strong>Due date:</strong> {{ optional($invoice->due_at)->toDateString() }}
        </p>
    @endif

    @if (! empty($invoice?->payment_url))
        <p>
            <a href="{{ $invoice->payment_url }}">Pay invoice now</a>
        </p>
    @endif

    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>
</html>

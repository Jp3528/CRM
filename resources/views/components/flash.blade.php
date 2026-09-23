@if (session('success'))
    <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="alert">{{ session('success') }}</div>
@endif
@if (session('status'))
    <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800" role="alert">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800" role="alert">{{ session('warning') }}</div>
@endif
@if (session('error'))
    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('error') }}</div>
@endif
@if (isset($errors) && $errors->any() && ($showAll ?? false))
    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

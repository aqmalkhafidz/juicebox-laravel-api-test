<x-mail::message>
# Welcome to Juicebox

Hi {{ $user->name }},

Your account is ready. You can now create posts and check the current weather in Perth.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

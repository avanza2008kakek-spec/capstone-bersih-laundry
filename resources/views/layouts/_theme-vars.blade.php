{{--
    Warna tema pilihan owner (Pengaturan Usaha → Tema web).
    Dipasang SETELAH style bawaan tiap layout, sehingga menimpa nilai variabel warna --teal, --mint, dst.
--}}
@php $themeVars = \App\Support\Themes::vars(app(\App\Support\Settings::class)->themeKey()); @endphp
<style>
    :root {
        @foreach($themeVars as $var => $color)
            {{ $var }}: {{ $color }};
        @endforeach
    }
</style>

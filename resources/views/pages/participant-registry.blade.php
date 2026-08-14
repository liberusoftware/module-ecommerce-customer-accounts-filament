{{-- The registry is a table on a page rather than a resource, because there is
     no model behind it: it is configuration a human wrote. --}}
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>

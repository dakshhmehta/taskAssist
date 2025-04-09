<x-filament::page>
    <div x-data="{ drTotal: 0, crTotal }" x-init="
        drTotal = [...document.querySelectorAll('.dr-cell')]
            .map(td => parseFloat(td.textContent.trim()) || 0)
            .reduce((a, b) => a + b, 0);

        crTotal = [...document.querySelectorAll('.cr-cell')]
            .map(td => parseFloat(td.textContent.trim()) || 0)
            .reduce((a, b) => a + b, 0);
    ">
        {{ $this->table }}

        <!-- Client-Side Total Summary -->
        <div class="p-4 flex justify-end">
            <p>
                <strong>Net P/L:</strong> 
                <span x-text="crTotal.toFixed(2) - drTotal.toFixed(2)"></span>
            </p>
        </div>
    </div>
</x-filament::page>

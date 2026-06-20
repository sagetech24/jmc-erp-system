<x-layouts::app :title="__('Procurement')">
    <div class="flex w-full flex-1 flex-col gap-8">
        <div>
            <flux:heading size="xl">{{ __('Procurement') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Follow the procurement cycle from request to stocked inventory.') }}</flux:text>
        </div>

        <flux:card class="w-full p-6 sm:p-10 border-none">
            @php
                $nodeClass = 'group flex size-24 sm:size-50 flex-col items-center justify-center rounded-xl text-center shadow-sm transition hover:-translate-y-1 hover:shadow-md dark:border-zinc-600 dark:hover:border-zinc-400';
                $labelClass = 'mt-2 px-2 text-md font-sans font-medium leading-tight text-zinc-700 group-hover:text-zinc-900 dark:text-zinc-200 dark:group-hover:text-white';
                $iconClass = 'size-10 text-zinc-500 group-hover:text-zinc-800 dark:group-hover:text-white';
                $arrowClass = 'text-zinc-300 dark:text-zinc-600';
            @endphp

            <div class="flex items-center justify-center gap-x-4 gap-y-4">
                <a href="{{ route('procurement.rfqs.index') }}" wire:navigate class="{{ $nodeClass }} bg-amber-300 border border-amber-500">
                    <flux:icon.document-text class="{{ $iconClass }}" />
                    <span class="{{ $labelClass }}">{{ __('Purchase Request') }}<br />{{ __('(RFQ)') }}</span>
                </a>

                <flux:icon.arrow-right class="{{ $arrowClass }} size-8 sm:size-14" />

                <a href="{{ route('procurement.purchase-orders.index') }}" wire:navigate class="{{ $nodeClass }} bg-blue-300 border border-blue-500">
                    <flux:icon.shopping-cart class="{{ $iconClass }}" />
                    <span class="{{ $labelClass }}">{{ __('Purchase Order') }}</span>
                </a>

                <flux:icon.arrow-right class="{{ $arrowClass }} size-8 sm:size-14" />
                
                <a href="{{ route('procurement.goods-receipts.index') }}" wire:navigate class="{{ $nodeClass }} bg-green-300 border border-green-500">
                    <flux:icon.arrow-down-tray class="{{ $iconClass }}" />
                    <span class="{{ $labelClass }}">{{ __('Goods Receipt') }}</span>
                </a>

                <flux:icon.arrow-right class="{{ $arrowClass }} size-8 sm:size-14" />

                <a href="{{ route('inventory.movements.index') }}" wire:navigate class="{{ $nodeClass }} bg-rose-300 border border-rose-500">
                    <flux:icon.clipboard-document-list class="{{ $iconClass }}" />
                    <span class="{{ $labelClass }}">{{ __('Inventory') }}</span>
                </a>

            </div>

            <flux:text class="mt-14 text-center text-sm">
                {{ __('Select a step to open the corresponding module. Goods receipts update inventory and complete the cycle.') }}
            </flux:text>
        </flux:card>
    </div>
</x-layouts::app>

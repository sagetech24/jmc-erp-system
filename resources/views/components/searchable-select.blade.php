@props([
    'label' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'results' => collect(),
    'searchWire' => null,
    'displayFormat' => 'name',
    'clearable' => false,
    'valueProperty' => null,
    'searchProperty' => null,
    'emptyLabel' => null,
])

@php
    $wireModel = $attributes->wire('model')->value();
    $wireModelDirective = collect(['wire:model.live', 'wire:model.blur', 'wire:model.change', 'wire:model'])
        ->first(fn (string $directive): bool => $attributes->has($directive)) ?? 'wire:model';
    $valueProp = $valueProperty ?? $wireModel;
    $searchProp = $searchWire ?? $searchProperty ?? ($wireModel.'Search');
    $placeholderText = $placeholder ?? __('Search…');
    $emptyLabelText = $emptyLabel ?? __('All');
    $formatOptionLabel = function (object $item) use ($displayFormat): string {
        return match ($displayFormat) {
            'name_sku' => $item->name.($item->sku ? ' ('.$item->sku.')' : ''),
            'supplier' => $item->name.($item->code ? ' · '.$item->code : ''),
            default => $item->name,
        };
    };

    $formatSelectedLabel = function (object $item) use ($displayFormat): string {
        return match ($displayFormat) {
            'name_sku', 'supplier' => $item->name,
            default => $item->name,
        };
    };
@endphp

<div
    wire:ignore.self
    x-data="{
        open: false,
        highlighted: -1,
        dropdownTop: 0,
        dropdownLeft: 0,
        dropdownWidth: 0,
        isDisabled: @js($disabled),
        optionCount() {
            return this.$refs.options?.children.length ?? 0;
        },
        updatePosition() {
            const input = this.$refs.input;
            if (! input) {
                return;
            }

            const rect = input.getBoundingClientRect();
            this.dropdownTop = rect.bottom + 4;
            this.dropdownLeft = rect.left;
            this.dropdownWidth = rect.width;
        },
        openDropdown() {
            if (this.isDisabled) {
                return;
            }

            this.open = true;
            this.highlighted = -1;
            this.$nextTick(() => this.updatePosition());
        },
        close() {
            this.open = false;
            this.highlighted = -1;
        },
        highlightNext() {
            const count = this.optionCount();
            if (count === 0) {
                return;
            }

            this.highlighted = this.highlighted < count - 1 ? this.highlighted + 1 : 0;
        },
        highlightPrev() {
            const count = this.optionCount();
            if (count === 0) {
                return;
            }

            this.highlighted = this.highlighted > 0 ? this.highlighted - 1 : count - 1;
        },
        selectHighlighted() {
            const count = this.optionCount();
            if (this.highlighted >= 0 && this.highlighted < count) {
                this.$refs.options?.children[this.highlighted]?.querySelector('button')?.click();
            }
        },
        init() {
            const reposition = () => {
                if (this.open) {
                    this.updatePosition();
                }
            };

            window.addEventListener('resize', reposition);
            window.addEventListener('scroll', reposition, true);

            return () => {
                window.removeEventListener('resize', reposition);
                window.removeEventListener('scroll', reposition, true);
            };
        },
    }"
    x-effect="if (open) { $nextTick(() => updatePosition()) }"
    @click.outside="close()"
    class="relative"
    {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:model.blur', 'wire:model.change'])->class('') }}
>
    <flux:field>
        @if ($label)
            <flux:label>
                {{ $label }}
                @if ($required)
                    <span class="text-red-500">*</span>
                @endif
            </flux:label>
        @endif

        <div class="relative">
            <input
                x-ref="input"
                type="text"
                autocomplete="off"
                @if (! $disabled)
                    wire:model.live.debounce.300ms="{{ $searchProp }}"
                @endif
                @disabled($disabled)
                @required($required)
                @focus="openDropdown()"
                @keydown.arrow-down.prevent="openDropdown(); highlightNext()"
                @keydown.arrow-up.prevent="highlightPrev()"
                @keydown.enter.prevent="selectHighlighted()"
                @keydown.escape.prevent="close()"
                placeholder="{{ $placeholderText }}"
                class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-800 shadow-xs placeholder:text-zinc-400 focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-400/30 disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:disabled:bg-zinc-900"
            />

            @if ($clearable && ! $disabled)
                <button
                    type="button"
                    wire:click="clearSearchableOption('{{ $valueProp }}', '{{ $searchProp }}')"
                    @click="close()"
                    class="absolute inset-y-0 end-0 flex items-center pe-2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200"
                    aria-label="{{ __('Clear selection') }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>

        <input type="hidden" {{ $wireModelDirective }}="{{ $wireModel }}" />
    </flux:field>

    <template x-teleport="body">
        <div
            x-show="open && ! isDisabled"
            x-cloak
            x-ref="dropdown"
            :style="`top: ${dropdownTop}px; left: ${dropdownLeft}px; width: ${dropdownWidth}px;`"
            class="fixed z-[9999] max-h-60 overflow-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-600 dark:bg-zinc-800"
            @mousedown.prevent
        >
            <ul x-ref="options" role="listbox" class="divide-y divide-zinc-100 dark:divide-zinc-700">
                @if ($clearable)
                    <li>
                        <button
                            type="button"
                            wire:click="clearSearchableOption('{{ $valueProp }}', '{{ $searchProp }}')"
                            @click="close()"
                            :class="highlighted === 0 ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
                            @mouseenter="highlighted = 0"
                            class="flex w-full cursor-pointer px-3 py-2 text-start text-sm text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            role="option"
                        >
                            {{ $emptyLabelText }}
                        </button>
                    </li>
                @endif

                @forelse ($results as $index => $item)
                    @php
                        $optionIndex = $clearable ? $index + 1 : $index;
                        $optionLabel = $formatOptionLabel($item);
                        $selectedLabel = $formatSelectedLabel($item);
                    @endphp
                    <li wire:key="searchable-option-{{ $valueProp }}-{{ $item->id }}">
                        <button
                            type="button"
                            wire:click="selectSearchableOption('{{ $valueProp }}', '{{ $searchProp }}', {{ $item->id }}, @js($selectedLabel))"
                            @click="close()"
                            :class="highlighted === {{ $optionIndex }} ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
                            @mouseenter="highlighted = {{ $optionIndex }}"
                            class="flex w-full cursor-pointer px-3 py-2 text-start text-sm text-zinc-800 hover:bg-zinc-100 dark:text-zinc-100 dark:hover:bg-zinc-700"
                            role="option"
                        >
                            {{ $optionLabel }}
                        </button>
                    </li>
                @empty
                    <li class="px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No results found') }}
                    </li>
                @endforelse
            </ul>
        </div>
    </template>
</div>

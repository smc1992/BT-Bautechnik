<button {{ $attributes->merge(['type' => 'button', 'class' => 'ui-button ui-button-secondary disabled:opacity-50']) }}>{{ $slot }}</button>

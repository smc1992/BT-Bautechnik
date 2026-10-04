<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-button ui-button-primary disabled:opacity-50']) }}>{{ $slot }}</button>

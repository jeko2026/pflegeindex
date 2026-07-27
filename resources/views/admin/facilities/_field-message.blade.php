@props(['field', 'helper' => null])

<span class="admin-field__message{{ $errors->has($field) ? ' admin-field__message--error' : '' }}" @if(!$errors->has($field) && blank($helper)) aria-hidden="true" @endif>
    @if($errors->has($field))
        {{ $errors->first($field) }}
    @elseif(filled($helper))
        {{ $helper }}
    @else
        &nbsp;
    @endif
</span>

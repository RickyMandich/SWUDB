@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-300 dark:scheme-dark focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-xs']) }}>
    {{ $slot }}
</select>

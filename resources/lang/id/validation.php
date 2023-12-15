<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Kolom :attribute must be accepted.',
    'active_url' => 'Kolom :attribute is not a valid URL.',
    'after' => 'Kolom :attribute must be a date after :date.',
    'after_or_equal' => 'Kolom :attribute must be a date after or equal to :date.',
    'alpha' => 'Kolom :attribute may only contain letters.',
    'alpha_dash' => 'Kolom :attribute may only contain letters, numbers, dashes and underscores.',
    'alpha_num' => 'Kolom :attribute may only contain letters and numbers.',
    'array' => 'Kolom :attribute must be an array.',
    'before' => 'Kolom :attribute must be a date before :date.',
    'before_or_equal' => 'Kolom :attribute must be a date before or equal to :date.',
    'between' => [
        'numeric' => 'Kolom :attribute must be between :min and :max.',
        'file' => 'Kolom :attribute must be between :min and :max kilobytes.',
        'string' => 'Kolom :attribute must be between :min and :max characters.',
        'array' => 'Kolom :attribute must have between :min and :max items.',
    ],
    'boolean' => 'Kolom :attribute field must be true or false.',
    'confirmed' => "Konfirmasi ':attribute' tidak sesuai", #edited
    'date' => "Kolom ':attribute' harus berupa tanggal yang sesuai.", #edited
    'date_equals' => 'Kolom :attribute must be a date equal to :date.',
    'date_format' => 'Kolom :attribute does not match the format :format.',
    'different' => 'Kolom :attribute and :other must be different.',
    'digits' => 'Kolom :attribute must be :digits digits.',
    'digits_between' => 'Kolom :attribute must be between :min and :max digits.',
    'dimensions' => 'Kolom :attribute has invalid image dimensions.',
    'distinct' => 'Kolom :attribute field has a duplicate value.',
    'email' => "Kolom ':attribute' harus berupa alamat surel yang valid.", #edited
    'ends_with' => 'Kolom :attribute must end with one of the following: :values.',
    'exists' => 'Kolom :attribute tidak valid.',
    'file' => 'Kolom :attribute harus berupa file.',
    'filled' => 'Kolom :attribute field must have a value.',
    'gt' => [
        'numeric' => 'Kolom :attribute must be greater than :value.',
        'file' => 'Kolom :attribute must be greater than :value kilobytes.',
        'string' => 'Kolom :attribute must be greater than :value characters.',
        'array' => 'Kolom :attribute must have more than :value items.',
    ],
    'gte' => [
        'numeric' => 'Kolom :attribute must be greater than or equal :value.',
        'file' => 'Kolom :attribute must be greater than or equal :value kilobytes.',
        'string' => 'Kolom :attribute must be greater than or equal :value characters.',
        'array' => 'Kolom :attribute must have :value items or more.',
    ],
    'image' => 'Kolom :attribute must be an image.',
    'in' => 'selected :attribute is invalid.',
    'in_array' => 'Kolom :attribute field does not exist in :other.',
    'integer' => 'Kolom :attribute must be an integer.',
    'ip' => 'Kolom :attribute must be a valid IP address.',
    'ipv4' => 'Kolom :attribute must be a valid IPv4 address.',
    'ipv6' => 'Kolom :attribute must be a valid IPv6 address.',
    'json' => 'Kolom :attribute must be a valid JSON string.',
    'lt' => [
        'numeric' => 'Kolom :attribute must be less than :value.',
        'file' => 'Kolom :attribute must be less than :value kilobytes.',
        'string' => 'Kolom :attribute must be less than :value characters.',
        'array' => 'Kolom :attribute must have less than :value items.',
    ],
    'lte' => [
        'numeric' => 'Kolom :attribute must be less than or equal :value.',
        'file' => 'Kolom :attribute must be less than or equal :value kilobytes.',
        'string' => 'Kolom :attribute must be less than or equal :value characters.',
        'array' => 'Kolom :attribute must not have more than :value items.',
    ],
    'max' => [ #edited
        'numeric' => "Kolom ':attribute' maksimal berjumlah :max.",
        'file' => "Kolom ':attribute' maksimal berukuran :max Kb.",
        'string' => "Kolom ':attribute' maksimal berukuran :max karakter.",
        'array' => "Kolom ':attribute' maksimal berjumlah :max item.",
    ],
    'mimes' => 'Kolom :attribute must be a file of type: :values.',
    'mimetypes' => 'Kolom :attribute must be a file of type: :values.',
    'min' => [ #edited
        'numeric' => "Kolom ':attribute' minimal berjumlah :min.",
        'file' => "Kolom ':attribute' minimal berukuran :min kilobytes.",
        'string' => "Kolom ':attribute' minimal berukuran :min characters.",
        'array' => "Kolom ':attribute' minimal berjumlah :min item.",
    ],
    'not_in' => 'selected :attribute is invalid.',
    'not_regex' => 'Kolom :attribute format is invalid.',
    'numeric' => 'Kolom :attribute must be a number.',
    'password' => 'password is incorrect.',
    'present' => 'Kolom :attribute field must be present.',
    'regex' => "Kolom ':attribute' tidak sesuai format.", #edited
    'required' => "Kolom ':attribute' wajib diisi.", #edited
    'required_if' => 'Kolom :attribute field is required when :other is :value.',
    'required_unless' => 'Kolom :attribute field is required unless :other is in :values.',
    'required_with' => 'Kolom :attribute field is required when :values is present.',
    'required_with_all' => 'Kolom :attribute field is required when :values are present.',
    'required_without' => 'Kolom :attribute field is required when :values is not present.',
    'required_without_all' => 'Kolom :attribute field is required when none of :values are present.',
    'same' => 'Kolom :attribute and :other must match.',
    'size' => [
        'numeric' => 'Kolom :attribute must be :size.',
        'file' => 'Kolom :attribute must be :size kilobytes.',
        'string' => 'Kolom :attribute must be :size characters.',
        'array' => 'Kolom :attribute must contain :size items.',
    ],
    'starts_with' => 'Kolom :attribute must start with one of the following: :values.',
    'string' => 'Kolom :attribute must be a string.',
    'timezone' => 'Kolom :attribute must be a valid zone.',
    'unique' => "':attribute' tidak dapat digunakan.", #edited
    'uploaded' => 'Kolom :attribute failed to upload.',
    'url' => 'Kolom :attribute format is invalid.',
    'uuid' => 'Kolom :attribute must be a valid UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];

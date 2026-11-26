<?php

return [

    /*
    | Arabic validation messages for the rules this app uses. Field names
    | given by a form in French stay in French inside the sentence.
    */

    'accepted' => 'يجب قبول :attribute.',
    'after' => 'يجب أن يكون :attribute تاريخًا بعد :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخًا مساويًا لـ :date أو بعده.',
    'array' => 'يجب أن يكون :attribute قائمة.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخًا مساويًا لـ :date أو قبله.',
    'boolean' => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'يجب أن يكون :attribute تاريخًا صالحًا.',
    'date_format' => 'يجب أن يطابق :attribute الصيغة :format.',
    'email' => 'يجب أن يكون :attribute عنوان بريد إلكتروني صالحًا.',
    'enum' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'exists' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'file' => 'يجب أن يكون :attribute ملفًا.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.',
    'lowercase' => 'يجب أن يكون :attribute بأحرف صغيرة.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عناصر.',
        'file' => 'يجب ألا يتجاوز حجم :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا يتجاوز :attribute القيمة :max.',
        'string' => 'يجب ألا يتجاوز :attribute :max حرفًا.',
    ],
    'mimes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'min' => [
        'numeric' => 'يجب أن يكون :attribute على الأقل :min.',
        'string' => 'يجب أن يحتوي :attribute على الأقل على :min حروف.',
    ],
    'not_in' => 'القيمة المختارة لـ :attribute غير صالحة.',
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'password' => [
        'letters' => 'يجب أن يحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهر :attribute في تسريب للمعطيات. يرجى اختيار غيره.',
    ],
    'regex' => 'صيغة :attribute غير صالحة.',
    'required' => 'حقل :attribute إلزامي.',
    'required_if' => 'حقل :attribute إلزامي عندما يكون :other هو :value.',
    'required_with' => 'حقل :attribute إلزامي عند وجود :values.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'unique' => 'قيمة :attribute مستعملة من قبل.',

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'role' => 'الدور',
        'user_id' => 'الموظف',
        'date_debut' => 'تاريخ البداية',
        'date_fin' => 'تاريخ النهاية',
        'type_conge' => 'نوع الإجازة',
        'etat' => 'الحالة',
        'comment' => 'التعليق',
    ],

];

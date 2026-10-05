<?php

namespace App\Enums;

enum ElmTemplates: string
{
    case SUBMIT_REQUEST = 'ELM_TEMPLATE_SUBMIT_REQUEST';

    public static function getTemplateId(string $template): string
    {
        return config('app.elm.'.$template);
    }

    /**
     * يرجع نص التمبلت (بدون استبدال المتغيرات لسه)
     * حسب template_id الحقيقي الجاي من علم (TAHEIYA-TMPL-03, TAHEIYA-TMPL-04...)
     */
    public static function getTemplateText(string $templateId): ?string
    {
        return match ($templateId) {
            // 'TAHEIYA-TMPL-01'=> "عزيزي العميل، نفيدكم بوجود مطالبة مالية لدى شركة #VAR01 بمبلغ وقدره #VAR02 ، لمزيد من المعلومات يرجى الاطلاع على المستندات من خلال الرابط  #VAR03
            //             تذكر دائماً أن تهيئة لن تطلب أي أرقام سرية خاصة بكم بأي حال من الأحوال",

            // 'TAHEIYA-TMPL-02'=>"عزيزي العميل، نفيدكم بوجود مطالبة مالية لدى شركة #VAR01  رقم المرجع  #VAR02 بمبلغ وقدره  #VAR03 ريال سعودي, لمزيد من المعلومات يرجى الاطلاع على المستندات من خلال الرابط  #VAR04 
            //         تذكر دائماً أن تهيئة لن تطلب أي أرقام سرية خاصة بكم بأي حال من الأحوال",

            'TAHEIYA-TMPL-03' => "عزيزي عميل تكافل الراجحي،
                     نحيطكم علماً بوجود مطالبة مالية مستحقة تحت المطالبة رقم #VAR01  بمبلغ وقدره #VAR02  ريال سعودي, لمزيد من المعلومات يرجى الاطلاع على المستندات من خلال الرابط #VAR03 ، تذكر دائماً أن تكافل الراجحي لن تطلب أي أرقام سرية خاصة بكم بأي حال من الأحوال ولمزيد من المعلومات تواصل معنا على رقم 920004414",

            'TAHEIYA-TMPL-04' => "عزيزي العميل، نفيدكم بوجود مطالبة مالية لدى شركة تكافل الراجحي رقم المطالبة #VAR04 بمبلغ وقدره #VAR02 ريال سعودي, لمزيد من المعلومات يرجى الاطلاع على المستندات من خلال الرابط #VAR03
                     تذكر دائماً أن تكافل الراجحي لن تطلب أي أرقام سرية خاصة بكم بأي حال من الأحوال ولمزيد من المعلومات تواصل معنا على رقم 920004414 او على الرقم 8001184444",

            default => null,
        };
    }

    /**
     * يرجع أسماء المتغيرات المستخدمة في كل تمبلت وترتيبها
     * عشان الاستبدال يبقى صح لكل تمبلت لوحده
     */
    public static function getTemplateVarsMap(string $templateId): array
    {
        return match ($templateId) {
            // 'TAHEIYA-TMPL-01' => ['VAR01', 'VAR02', 'VAR03'], // claim_number, amount, url
            // 'TAHEIYA-TMPL-02' => ['VAR01', 'VAR02', 'VAR03'], // claim_number, amount, url
            'TAHEIYA-TMPL-03' => ['VAR01', 'VAR02', 'VAR03'], // claim_number, amount, url
            'TAHEIYA-TMPL-04' => ['VAR02', 'VAR03', 'VAR04'], // amount, url, claim_number
            default => [],
        };
    }
}

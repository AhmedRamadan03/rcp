let datepicker;

function setLanguage(lang) {
    if (datepicker) {
        datepicker.destroy();  // Destroy previous instance
    }

    let localeConfig = lang === 'ar' ? flatpickr.l10ns.ar : flatpickr.l10ns.default;

    // Customize the locale for English to include month numbers
    if (lang === 'en') {
        localeConfig = {
            ...localeConfig,
            months: {
                shorthand: ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10", "11", "12"],
                longhand: [
                    "1- January", "2- February", "3- March", "4- April", "5- May", "6- June",
                    "7- July", "8- August", "9- September", "10- October", "11- November", "12- December"
                ],
            }
        }
    }

    datepicker = flatpickr(".datepicker", {
        locale: {
            ...localeConfig,  // Apply the customized locale
            firstDayOfWeek: 6  // Set Saturday as the first day of the week
        },
        dateFormat: "Y-m-d",

        onOpen: function(selectedDates, dateStr, instance) {
            // Add RTL class for Arabic
            if (lang === 'ar') {
                instance.calendarContainer.classList.add('rtl');
            } else {
                instance.calendarContainer.classList.remove('rtl');
            }
        }
    });
}

let appLang = document.getElementById("flatpickr-init").getAttribute('app-lang');

// Initialize with the specified language
setLanguage(appLang);

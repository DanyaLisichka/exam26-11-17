document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const root = document.getElementById('bookingCalendar');
    if (!root) return;

    const roomId = root.dataset.roomId;
    const checkInInput = document.getElementById('check_in');
    const checkOutInput = document.getElementById('check_out');
    const monthLabel = document.getElementById('calendarMonth');
    const daysContainer = document.getElementById('calendarDays');
    const message = document.getElementById('calendarMessage');
    const previousButton = document.getElementById('calendarPrevious');
    const nextButton = document.getElementById('calendarNext');
    const clearButton = document.getElementById('clearBookingDates');

    const state = {
        today: '',
        approved: [],
        view: new Date(),
        mode: 'check_in',
        checkIn: parseRuDate(checkInInput.value),
        checkOut: parseRuDate(checkOutInput.value),
        loaded: false
    };

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function toIso(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
    }

    function fromIso(value) {
        const parts = value.split('-').map(Number);
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function toRu(value) {
        if (!value) return '';
        const parts = value.split('-');
        return parts[2] + '.' + parts[1] + '.' + parts[0];
    }

    function parseRuDate(value) {
        const match = /^([0-3]\d)\.([01]\d)\.(\d{4})$/.exec(value || '');
        if (!match) return '';
        const date = new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
        if (date.getFullYear() !== Number(match[3]) || date.getMonth() !== Number(match[2]) - 1 || date.getDate() !== Number(match[1])) {
            return '';
        }
        return toIso(date);
    }

    function isNightBooked(iso) {
        return state.approved.some(function (period) {
            return iso >= period.check_in && iso < period.check_out;
        });
    }

    function isCheckoutBlocked(iso) {
        if (!state.checkIn || iso <= state.checkIn) return true;
        // Бронирование занимает ночи [заезд, выезд). Поэтому выехать
        // в день начала следующего одобренного бронирования можно.
        return state.approved.some(function (period) {
            return state.checkIn < period.check_out && iso > period.check_in;
        });
    }

    function setMessage(text, isError) {
        message.textContent = text;
        message.classList.toggle('text-danger', Boolean(isError));
        message.classList.toggle('text-secondary', !isError);
    }

    function updateInputs() {
        checkInInput.value = toRu(state.checkIn);
        checkOutInput.value = toRu(state.checkOut);
    }

    function renderCalendar() {
        if (!state.loaded) return;

        const year = state.view.getFullYear();
        const month = state.view.getMonth();
        const firstDay = new Date(year, month, 1);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const offset = (firstDay.getDay() + 6) % 7;
        const currentMonth = new Date(year, month, 1);
        const minMonth = fromIso(state.today);
        minMonth.setDate(1);

        monthLabel.textContent = new Intl.DateTimeFormat('ru-RU', { month: 'long', year: 'numeric' }).format(firstDay);
        previousButton.disabled = currentMonth <= minMonth;
        daysContainer.replaceChildren();

        for (let i = 0; i < offset; i += 1) {
            const empty = document.createElement('span');
            empty.className = 'calendar-day calendar-day--empty';
            empty.setAttribute('aria-hidden', 'true');
            daysContainer.appendChild(empty);
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = new Date(year, month, day);
            const iso = toIso(date);
            const past = iso < state.today;
            const booked = isNightBooked(iso);
            const blocked = state.mode === 'check_in'
                ? (past || booked)
                : (past || isCheckoutBlocked(iso));

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'calendar-day';
            button.textContent = String(day);
            button.dataset.date = iso;
            button.disabled = blocked;
            button.setAttribute('aria-label', day + ' ' + monthLabel.textContent);

            if (past) button.classList.add('is-past');
            if (booked) button.classList.add('is-unavailable');
            if (state.checkIn === iso || state.checkOut === iso) button.classList.add('is-selected');
            if (state.checkIn && state.checkOut && iso > state.checkIn && iso < state.checkOut) {
                button.classList.add('is-in-range');
            }
            if (state.mode === 'check_out' && booked && !blocked) {
                button.classList.add('is-checkout-boundary');
                button.title = 'Можно выбрать как дату выезда перед следующим бронированием';
            } else if (booked) {
                button.title = 'Занято: уже есть одобренное бронирование';
            }

            button.addEventListener('click', function () {
                chooseDate(iso);
            });
            daysContainer.appendChild(button);
        }

        const usedCells = offset + daysInMonth;
        const remaining = (7 - (usedCells % 7)) % 7;
        for (let i = 0; i < remaining; i += 1) {
            const empty = document.createElement('span');
            empty.className = 'calendar-day calendar-day--empty';
            empty.setAttribute('aria-hidden', 'true');
            daysContainer.appendChild(empty);
        }
    }

    function chooseDate(iso) {
        if (state.mode === 'check_in') {
            if (iso < state.today || isNightBooked(iso)) return;
            state.checkIn = iso;
            state.checkOut = '';
            state.mode = 'check_out';
            updateInputs();
            setMessage('Дата заезда выбрана. Теперь выберите дату выезда. Выезд в день начала следующего бронирования разрешён.', false);
        } else {
            if (isCheckoutBlocked(iso)) return;
            state.checkOut = iso;
            updateInputs();
            setMessage('Период выбран: ' + toRu(state.checkIn) + ' — ' + toRu(state.checkOut) + '. Перед сохранением сервер повторно проверит доступность.', false);
        }
        renderCalendar();
    }

    checkInInput.addEventListener('click', function () {
        state.mode = 'check_in';
        state.view = state.checkIn ? fromIso(state.checkIn) : fromIso(state.today);
        setMessage('Выберите свободную дату заезда.', false);
        renderCalendar();
    });

    checkOutInput.addEventListener('click', function () {
        if (!state.checkIn) {
            state.mode = 'check_in';
            setMessage('Сначала выберите дату заезда.', false);
        } else {
            state.mode = 'check_out';
            state.view = fromIso(state.checkIn);
            setMessage('Выберите дату выезда позже даты заезда.', false);
        }
        renderCalendar();
    });

    previousButton.addEventListener('click', function () {
        state.view = new Date(state.view.getFullYear(), state.view.getMonth() - 1, 1);
        renderCalendar();
    });

    nextButton.addEventListener('click', function () {
        state.view = new Date(state.view.getFullYear(), state.view.getMonth() + 1, 1);
        renderCalendar();
    });

    clearButton.addEventListener('click', function () {
        state.checkIn = '';
        state.checkOut = '';
        state.mode = 'check_in';
        state.view = fromIso(state.today);
        updateInputs();
        setMessage('Даты очищены. Выберите дату заезда.', false);
        renderCalendar();
    });

    fetch('availability.php?room_id=' + encodeURIComponent(roomId), {
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
    })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (data) {
            if (!data || !Array.isArray(data.bookings) || !data.today) {
                throw new Error('Некорректный ответ сервера');
            }
            state.today = data.today;
            state.approved = data.bookings;
            state.loaded = true;
            state.view = state.checkIn ? fromIso(state.checkIn) : fromIso(state.today);
            if (state.checkIn && state.checkOut) {
                const conflicts = state.approved.some(function (period) {
                    return state.checkIn < period.check_out && state.checkOut > period.check_in;
                });
                if (conflicts) {
                    state.checkOut = '';
                    updateInputs();
                    setMessage('Ранее выбранный период теперь занят. Выберите другие даты.', true);
                }
            }
            if (state.checkIn && state.checkOut) {
                state.mode = 'check_out';
                setMessage('Период выбран: ' + toRu(state.checkIn) + ' — ' + toRu(state.checkOut) + '.', false);
            } else if (state.checkIn) {
                state.mode = 'check_out';
                setMessage('Выберите дату выезда.', false);
            } else {
                state.mode = 'check_in';
                setMessage('Выберите свободную дату заезда.', false);
            }
            renderCalendar();
        })
        .catch(function () {
            setMessage('Не удалось загрузить занятые даты. Обновите страницу. Бронирование без проверки календаря не рекомендуется.', true);
            daysContainer.replaceChildren();
            previousButton.disabled = true;
            nextButton.disabled = true;
        });
});

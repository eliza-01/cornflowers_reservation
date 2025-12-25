(() => {
  const tg = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;

  // --- UI refs
  const screenTitle = document.getElementById('screenTitle');
  const screenSubtitle = document.getElementById('screenSubtitle');

  const screen1 = document.getElementById('screen1');
  const screen2 = document.getElementById('screen2');
  const screen3 = document.getElementById('screen3');
  const screen4 = document.getElementById('screen4');

  const prevMonthBtn = document.getElementById('prevMonthBtn');
  const nextMonthBtn = document.getElementById('nextMonthBtn');
  const monthLabel = document.getElementById('monthLabel');
  const calendarGrid = document.getElementById('calendarGrid');

  const tablesRow1 = document.getElementById('tablesRow1');
  const tablesRow2 = document.getElementById('tablesRow2');
  const tablesRow3 = document.getElementById('tablesRow3');
  const backToDateBtn = document.getElementById('backToDateBtn');

  const bookingList = document.getElementById('bookingList');
  const emptyHint = document.getElementById('emptyHint');
  const backToTablesBtn = document.getElementById('backToTablesBtn');
  const openCreateBtn = document.getElementById('openCreateBtn');

  const createForm = document.getElementById('createForm');
  const timeSelect = document.getElementById('timeSelect');
  const nameInput = document.getElementById('nameInput');
  const peopleInput = document.getElementById('peopleInput');
  const commentInput = document.getElementById('commentInput');
  const cancelCreateBtn = document.getElementById('cancelCreateBtn');
  const confirmCreateBtn = document.getElementById('confirmCreateBtn');

  const modal = document.getElementById('modal');
  const modalBackdrop = document.getElementById('modalBackdrop');
  const modalKv = document.getElementById('modalKv');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const editModalBtn = document.getElementById('editModalBtn');
  const deleteModalBtn = document.getElementById('deleteModalBtn');

  // --- state
  let selectedDate = null;      // 'YYYY-MM-DD'
  let selectedTableId = null;   // number
  let visibleYear = null;
  let visibleMonth = null;      // 0..11

  let bookingsCache = [];       // list for current table+date
  let tableCounts = {};         // { "1": 0, ... "27": 3 }

  // Требование: бронь только с 9 до 21 (последний слот 21:00)
  const openHour = 9;
  const closeHour = 22; // exclusive

  // create/edit mode
  let editingBookingId = null;  // null => create, number => edit
  let currentModalBooking = null;

  // --- helpers
  function pad2(n){ return String(n).padStart(2,'0'); }
  function toISODate(d){
    return `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;
  }
  function monthNameRu(m){
    const names = ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
    return names[m] || '—';
  }
  function showScreen(n){
    screen1.classList.toggle('hidden', n !== 1);
    screen2.classList.toggle('hidden', n !== 2);
    screen3.classList.toggle('hidden', n !== 3);
    screen4.classList.toggle('hidden', n !== 4);
  }
  function setTitle(step, subtitle){
    screenTitle.textContent = step;
    screenSubtitle.textContent = subtitle || '';
  }

  async function api(path, payload){
    const initData = tg ? (tg.initData || '') : '';
    const res = await fetch(path, {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ initData, ...payload })
    });
    const data = await res.json().catch(() => null);
    if (!data || !data.ok){
      const msg = (data && data.error) ? data.error : `Ошибка API: ${res.status}`;
      throw new Error(msg);
    }
    return data;
  }

  function toast(msg){
    if (tg && tg.showPopup){
      tg.showPopup({ title: 'Сообщение', message: msg, buttons: [{type:'ok'}] });
      return;
    }
    alert(msg);
  }

  async function confirmBox(msg){
    if (tg && tg.showPopup){
      return await new Promise((resolve) => {
        tg.showPopup({
          title: 'Подтвердите',
          message: msg,
          buttons: [
            { id: 'yes', type: 'default', text: 'Да' },
            { id: 'no', type: 'destructive', text: 'Нет' }
          ]
        }, (buttonId) => {
          resolve(buttonId === 'yes');
        });
      });
    }
    return confirm(msg);
  }

  function todayISO(){
    // на фронте — по локальному времени устройства (бэк всё равно запрещает прошлое)
    return toISODate(new Date());
  }

  function statusClassByCount(cnt){
    // Требование: зелёный если броней нет. Желтый если 2-3. Красный 4-5.
    // Для 1 брони оставляем "зелёный" (таблица почти свободна). Для 6+ тоже красный.
    if (cnt >= 4) return 'status-red';
    if (cnt >= 2) return 'status-yellow';
    return 'status-green';
  }

  // --- calendar render
  function renderCalendar(year, month){
    visibleYear = year;
    visibleMonth = month;
    monthLabel.textContent = `${monthNameRu(month)} ${year}`;
    calendarGrid.innerHTML = '';

    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);

    // Monday-first index: 0..6
    const jsDay = first.getDay(); // 0=Sun..6=Sat
    const firstDow = (jsDay === 0) ? 6 : jsDay - 1;

    const min = new Date();
    const minYear = min.getFullYear();
    const minMonth = min.getMonth();

    prevMonthBtn.disabled = (year === minYear && month === minMonth);

    // days from prev month (пустые)
    for (let i=0; i<firstDow; i++){
      const cell = document.createElement('div');
      cell.className = 'day muted';
      cell.textContent = '';
      calendarGrid.appendChild(cell);
    }

    const today = todayISO();

    for (let day=1; day<=last.getDate(); day++){
      const d = new Date(year, month, day);
      const iso = toISODate(d);

      const cell = document.createElement('div');
      cell.className = 'day';
      cell.textContent = String(day);

      const isPast = iso < today;
      if (isPast){
        cell.classList.add('disabled');
      } else {
        cell.addEventListener('click', async () => {
          selectedDate = iso;

          await loadDaySummary(); // чтобы столы сразу окрасились правильно

          setTitle('Шаг 2 — Выбор стола', `Дата: ${selectedDate}`);
          showScreen(2);
          renderTables(); // перерисовать с цветами
        });
      }

      if (selectedDate === iso) cell.classList.add('selected');
      calendarGrid.appendChild(cell);
    }
  }

  // --- day summary (counts per table)
  async function loadDaySummary(){
    tableCounts = {};
    for (let i=1;i<=27;i++) tableCounts[String(i)] = 0;

    try{
      const data = await api('/api/get_day_summary.php', { date: selectedDate });
      tableCounts = data.counts || tableCounts;
    }catch(e){
      toast(e.message);
    }
  }

  // --- tables render
  function renderTables(){
    function btn(tableId){
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'table-btn';
      b.textContent = String(tableId);

      if (selectedTableId === tableId) b.classList.add('selected');

      const cnt = selectedDate ? (parseInt(tableCounts[String(tableId)] || 0, 10) || 0) : 0;
      if (selectedDate) b.classList.add(statusClassByCount(cnt));

      b.addEventListener('click', async () => {
        selectedTableId = tableId;
        setTitle(`Шаг 3 — Стол ${selectedTableId}`, `Дата: ${selectedDate}`);
        showScreen(3);
        await loadBookingsAndRender();
      });

      return b;
    }

    tablesRow1.innerHTML = '';
    tablesRow2.innerHTML = '';
    tablesRow3.innerHTML = '';

    for (let i=1;i<=10;i++) tablesRow1.appendChild(btn(i));
    for (let i=11;i<=20;i++) tablesRow2.appendChild(btn(i));
    for (let i=21;i<=27;i++) tablesRow3.appendChild(btn(i));
  }

  // --- bookings list
  async function loadBookingsAndRender(){
    bookingsCache = [];
    bookingList.innerHTML = '';
    emptyHint.classList.add('hidden');

    try{
      const data = await api('/api/get_bookings.php', { date: selectedDate, table_id: selectedTableId });
      bookingsCache = data.bookings || [];
    }catch(e){
      toast(e.message);
      return;
    }

    if (!bookingsCache.length){
      emptyHint.classList.remove('hidden');
    } else {
      for (const b of bookingsCache){
        const row = document.createElement('div');
        row.className = 'item';
        row.tabIndex = 0;

        const left = document.createElement('div');
        left.className = 'left';

        const main = document.createElement('div');
        main.className = 'main';
        main.textContent = `${b.booking_time} / ${b.customer_name} / ${b.people_count}`;

        const sub = document.createElement('div');
        sub.className = 'sub';
        sub.textContent = (b.comment && b.comment.trim()) ? b.comment.trim() : 'Без комментария';

        left.appendChild(main);
        left.appendChild(sub);

        const arrow = document.createElement('div');
        arrow.className = 'sub';
        arrow.textContent = '›';

        row.appendChild(left);
        row.appendChild(arrow);

        row.addEventListener('click', async () => {
          await openBookingModal(b.id);
        });

        bookingList.appendChild(row);
      }
    }

    // обновить окраску столов (counts) после загрузки/изменений
    await loadDaySummary();
    if (!screen2.classList.contains('hidden')) renderTables();
  }

  async function openBookingModal(id){
    try{
      const data = await api('/api/get_booking.php', { id });
      const b = data.booking;
      currentModalBooking = b;

      modalKv.innerHTML = '';
      const pairs = [
        ['Стол', String(b.table_id)],
        ['Дата', String(b.booking_date)],
        ['Время', String(b.booking_time)],
        ['Имя', String(b.customer_name)],
        ['Людей', String(b.people_count)],
        ['Комментарий', (b.comment && b.comment.trim()) ? b.comment.trim() : '—'],
      ];

      for (const [k,v] of pairs){
        const kEl = document.createElement('div');
        kEl.className = 'k';
        kEl.textContent = k;

        const vEl = document.createElement('div');
        vEl.className = 'v';
        vEl.textContent = v;

        modalKv.appendChild(kEl);
        modalKv.appendChild(vEl);
      }

      modal.classList.remove('hidden');
    }catch(e){
      toast(e.message);
    }
  }

  function closeModal(){
    modal.classList.add('hidden');
    currentModalBooking = null;
  }

  // --- create/edit booking
  function renderTimeSelect(allowTime /* "HH:MM" or null */){
    timeSelect.innerHTML = '';

    // занятые часы -> Set("HH:MM")
    const taken = new Set(bookingsCache.map(b => b.booking_time));

    for (let h=openHour; h<closeHour; h++){
      const t = `${pad2(h)}:00`;
      const opt = document.createElement('option');
      opt.value = t;

      const isTaken = taken.has(t);
      const canUse = !isTaken || (allowTime && allowTime === t);

      if (!canUse){
        opt.disabled = true;
        opt.textContent = `${t} (занято)`;
      } else {
        opt.textContent = t;
      }

      timeSelect.appendChild(opt);
    }

    // выставить значение
    if (allowTime){
      timeSelect.value = allowTime;
    } else {
      const firstEnabled = [...timeSelect.options].find(o => !o.disabled);
      if (firstEnabled) timeSelect.value = firstEnabled.value;
    }
  }

  async function submitBooking(){
    const time = timeSelect.value;
    const name = nameInput.value.trim();
    const people = parseInt(peopleInput.value, 10) || 0;
    const comment = commentInput.value.trim();

    if (!time){ toast('Выберите время'); return; }
    if (!name){ toast('Введите имя'); return; }
    if (people < 1){ toast('Укажите кол-во человек'); return; }

    try{
      if (editingBookingId){
        await api('/api/update_booking.php', {
          id: editingBookingId,
          time,
          name,
          people,
          comment
        });
      } else {
        await api('/api/create_booking.php', {
          date: selectedDate,
          table_id: selectedTableId,
          time,
          name,
          people,
          comment
        });
      }

      // назад к списку
      editingBookingId = null;
      confirmCreateBtn.textContent = 'Подтвердить';

      setTitle(`Шаг 3 — Стол ${selectedTableId}`, `Дата: ${selectedDate}`);
      showScreen(3);

      // чистим форму
      nameInput.value = '';
      peopleInput.value = '';
      commentInput.value = '';

      await loadBookingsAndRender();
    }catch(e){
      toast(e.message);
    }
  }

  // --- events
  prevMonthBtn.addEventListener('click', () => {
    if (prevMonthBtn.disabled) return;
    const d = new Date(visibleYear, visibleMonth, 1);
    d.setMonth(d.getMonth() - 1);
    renderCalendar(d.getFullYear(), d.getMonth());
  });

  nextMonthBtn.addEventListener('click', () => {
    const d = new Date(visibleYear, visibleMonth, 1);
    d.setMonth(d.getMonth() + 1);
    renderCalendar(d.getFullYear(), d.getMonth());
  });

  backToDateBtn.addEventListener('click', () => {
    setTitle('Шаг 1 — Выбор даты', '');
    showScreen(1);
  });

  backToTablesBtn.addEventListener('click', () => {
    setTitle('Шаг 2 — Выбор стола', `Дата: ${selectedDate}`);
    showScreen(2);
    renderTables();
  });

  openCreateBtn.addEventListener('click', async () => {
    editingBookingId = null;
    confirmCreateBtn.textContent = 'Подтвердить';

    setTitle(`Шаг 4 — Бронь (Стол ${selectedTableId})`, `Дата: ${selectedDate}`);
    showScreen(4);

    renderTimeSelect(null);
    nameInput.value = '';
    peopleInput.value = '';
    commentInput.value = '';
    nameInput.focus();
  });

  cancelCreateBtn.addEventListener('click', () => {
    editingBookingId = null;
    confirmCreateBtn.textContent = 'Подтвердить';

    setTitle(`Шаг 3 — Стол ${selectedTableId}`, `Дата: ${selectedDate}`);
    showScreen(3);
  });

  createForm.addEventListener('submit', (e) => {
    e.preventDefault();
    submitBooking();
  });

  modalBackdrop.addEventListener('click', closeModal);
  closeModalBtn.addEventListener('click', closeModal);

  editModalBtn.addEventListener('click', () => {
    if (!currentModalBooking) return;

    const b = currentModalBooking;

    // открываем форму редактирования
    editingBookingId = parseInt(b.id, 10);
    confirmCreateBtn.textContent = 'Сохранить';

    setTitle(`Шаг 4 — Редактирование (Стол ${b.table_id})`, `Дата: ${b.booking_date}`);
    showScreen(4);

    // текущие брони уже в bookingsCache (для выбранного стола/даты)
    renderTimeSelect(String(b.booking_time));
    nameInput.value = String(b.customer_name || '');
    peopleInput.value = String(b.people_count || '');
    commentInput.value = (b.comment && String(b.comment)) ? String(b.comment) : '';
    nameInput.focus();

    closeModal();
  });

  deleteModalBtn.addEventListener('click', async () => {
    if (!currentModalBooking) return;

    const b = currentModalBooking;
    const ok = await confirmBox(`Удалить бронь ${b.booking_date} ${b.booking_time} (стол ${b.table_id})?`);
    if (!ok) return;

    try{
      await api('/api/delete_booking.php', { id: parseInt(b.id, 10) });
      closeModal();
      await loadBookingsAndRender();
    }catch(e){
      toast(e.message);
    }
  });

  // --- init
  function init(){
    // iPhone/iOS: добавляем класс на body
    const ua = navigator.userAgent || '';
    const isIOS = /iPhone|iPad|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (isIOS) document.body.classList.add('ios');

    if (tg){
      tg.ready();
      tg.expand();
    }

    const now = new Date();
    visibleYear = now.getFullYear();
    visibleMonth = now.getMonth();

    setTitle('Шаг 1 — Выбор даты', '');
    showScreen(1);

    renderCalendar(visibleYear, visibleMonth);
    renderTables();
  }

  init();
})();

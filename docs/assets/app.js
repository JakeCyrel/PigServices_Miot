(() => {
    const config = window.PIG_SERVICES_SUPABASE || {};
    const setupNotice = document.getElementById('setup-notice');
    const authDialog = document.getElementById('auth-dialog');
    const authMessage = document.getElementById('auth-message');
    const serviceGrid = document.getElementById('service-grid');
    const memberArea = document.getElementById('member-area');
    const notice = document.getElementById('app-notice');
    const currentUser = document.getElementById('current-user');
    const requestDialog = document.getElementById('request-dialog');
    const conversation = document.getElementById('conversation');
    let client = null;
    let user = null;
    let profile = null;
    let activeRequest = null;
    let requests = [];

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);

    function setNotice(message, type = 'info') {
        notice.textContent = message;
        notice.dataset.type = type;
        notice.hidden = !message;
    }

    function setAuthMessage(message, type = 'info') {
        authMessage.textContent = message;
        authMessage.dataset.type = type;
        authMessage.hidden = !message;
    }

    function openAuth(mode = 'login') {
        document.getElementById('login-form').hidden = mode !== 'login';
        document.getElementById('signup-form').hidden = mode !== 'signup';
        setAuthMessage('');
        authDialog.showModal();
    }

    function showConnectedArea() {
        document.getElementById('sign-in-button').hidden = Boolean(user);
        document.getElementById('sign-up-button').hidden = Boolean(user);
        currentUser.hidden = !user;
        currentUser.textContent = user ? `${profile?.full_name || user.email} · ${profile?.role || 'member'}` : '';
        document.getElementById('sign-out-button').hidden = !user;
        memberArea.hidden = !user;
        document.getElementById('create-service-panel').hidden = !['male_owner', 'veterinarian'].includes(profile?.role);
        document.getElementById('admin-panel').hidden = profile?.role !== 'admin';
        if (!user) {
            conversation.hidden = true;
            serviceGrid.innerHTML = '<p class="empty-state">Sign in to browse active services and contact providers.</p>';
        }
    }

    async function loadProfile() {
        const { data, error } = await client.from('profiles').select('*').eq('id', user.id).single();
        if (error) throw error;
        profile = data;
        showConnectedArea();
        document.getElementById('profile-name').value = profile.full_name || '';
        document.getElementById('profile-phone').value = profile.phone || '';
        document.getElementById('profile-address').value = profile.address || '';
    }

    async function refreshData() {
        if (!user || !profile) return;
        await Promise.all([loadServices(), loadRequests()]);
        if (profile.role === 'admin') await loadAdminUsers();
    }

    async function loadServices() {
        const { data, error } = await client.from('services')
            .select('id,service_type,pig_name,breed,age_months,location,description,owner_id,created_at,profiles!services_owner_id_fkey(full_name,phone)')
            .eq('is_active', true).order('created_at', { ascending: false });
        if (error) throw error;
        if (!data.length) {
            serviceGrid.innerHTML = '<p class="empty-state">No services are listed yet.</p>';
            return;
        }
        serviceGrid.innerHTML = data.map((service) => `
            <article class="service-card">
                <div class="service-top"><span class="tag">${escapeHtml(service.service_type)}</span><span class="tag">${escapeHtml(service.breed)}</span></div>
                <h3>${escapeHtml(service.pig_name)}</h3>
                <p>${escapeHtml(service.description)}</p>
                <small>${escapeHtml(service.location)} · ${Number(service.age_months)} months · ${escapeHtml(service.profiles?.full_name || 'Provider')}</small>
                <div class="service-contact"><span>Provider contact</span>${service.profiles?.phone ? `<a href="tel:${escapeHtml(service.profiles.phone)}">${escapeHtml(service.profiles.phone)}</a>` : '<span>Contact after request</span>'}</div>
                ${['male_owner', 'female_owner'].includes(profile.role) && service.owner_id !== user.id ? `<button class="button primary small request-service" data-service="${service.id}">Request service</button>` : ''}
            </article>`).join('');
        serviceGrid.querySelectorAll('.request-service').forEach((button) => button.addEventListener('click', () => {
            document.getElementById('request-service-id').value = button.dataset.service;
            document.getElementById('request-form').reset();
            document.getElementById('request-service-id').value = button.dataset.service;
            requestDialog.showModal();
        }));
    }

    async function loadRequests() {
        const { data, error } = await client.from('requests')
            .select('id,reference_no,service_id,requester_id,preferred_date,notes,status,created_at,services!requests_service_id_fkey(id,pig_name,owner_id,profiles!services_owner_id_fkey(full_name)),profiles!requests_requester_id_fkey(full_name)')
            .order('created_at', { ascending: false });
        if (error) throw error;
        requests = data;
        const list = document.getElementById('request-list');
        if (!data.length) {
            list.innerHTML = '<p class="empty-state">No service requests yet.</p>';
            return;
        }
        list.innerHTML = data.map((request) => {
            const owner = request.services?.owner_id === user.id;
            const canUpdate = profile.role === 'admin' || owner;
            const otherName = owner ? request.profiles?.full_name : request.services?.profiles?.full_name;
            const statusControl = canUpdate ? `<select class="request-status" data-request="${request.id}" aria-label="Request status"><option ${request.status === 'Pending' ? 'selected' : ''}>Pending</option><option ${request.status === 'Approved' ? 'selected' : ''}>Approved</option><option ${request.status === 'Rejected' ? 'selected' : ''}>Rejected</option><option ${request.status === 'Completed' ? 'selected' : ''}>Completed</option><option ${request.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option></select>` : `<span class="status ${request.status.toLowerCase()}">${escapeHtml(request.status)}</span>`;
            return `<article class="request-row"><div><strong>${escapeHtml(request.services?.pig_name || 'Service request')}</strong><span>${escapeHtml(request.reference_no)} · ${escapeHtml(otherName || 'Participant')} · ${escapeHtml(request.preferred_date)}</span></div><div class="request-actions">${statusControl}<button class="button light small open-conversation" data-request="${request.id}">Messages</button></div></article>`;
        }).join('');
        list.querySelectorAll('.request-status').forEach((select) => select.addEventListener('change', async () => {
            const { error: updateError } = await client.from('requests').update({ status: select.value }).eq('id', select.dataset.request);
            setNotice(updateError ? updateError.message : 'Request status updated.', updateError ? 'error' : 'success');
            await loadRequests();
        }));
        list.querySelectorAll('.open-conversation').forEach((button) => button.addEventListener('click', () => openConversation(button.dataset.request)));
    }

    async function openConversation(requestId) {
        activeRequest = requests.find((request) => request.id === requestId);
        if (!activeRequest) return;
        const otherId = activeRequest.requester_id === user.id ? activeRequest.services.owner_id : activeRequest.requester_id;
        document.getElementById('conversation-title').textContent = `Messages · ${activeRequest.services.pig_name} · ${activeRequest.reference_no}`;
        conversation.hidden = false;
        const { data, error } = await client.from('messages').select('*').eq('request_id', requestId).order('created_at');
        if (error) { setNotice(error.message, 'error'); return; }
        const profileIds = [...new Set(data.map((item) => item.sender_id))];
        const nameMap = {};
        if (profileIds.length) {
            const result = await client.from('profiles').select('id,full_name').in('id', profileIds);
            (result.data || []).forEach((person) => { nameMap[person.id] = person.full_name; });
        }
        const list = document.getElementById('message-list');
        list.innerHTML = data.length ? data.map((item) => `<article class="message-bubble ${item.sender_id === user.id ? 'mine' : 'theirs'}"><strong>${escapeHtml(item.sender_id === user.id ? 'You' : nameMap[item.sender_id] || 'Participant')}</strong><p>${escapeHtml(item.message)}</p><time>${new Date(item.created_at).toLocaleString()}</time></article>`).join('') : '<p class="empty-state">No messages yet. Send the first message.</p>';
        const unreadIds = data.filter((item) => item.recipient_id === user.id && !item.read_at).map((item) => item.id);
        if (unreadIds.length) await client.from('messages').update({ read_at: new Date().toISOString() }).in('id', unreadIds);
        document.getElementById('message-recipient-id').value = otherId;
        list.scrollTop = list.scrollHeight;
        conversation.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function loadAdminUsers() {
        const { data, error } = await client.from('profiles').select('id,full_name,email,phone,role,is_verified,created_at').order('created_at', { ascending: false });
        if (error) { setNotice(error.message, 'error'); return; }
        document.getElementById('admin-user-list').innerHTML = data.map((person) => `<div class="request-row"><div><strong>${escapeHtml(person.full_name)}</strong><span>${escapeHtml(person.email)} · ${escapeHtml(person.role)}</span></div><span class="status ${person.is_verified ? 'approved' : 'pending'}">${person.is_verified ? 'Active' : 'Pending'}</span></div>`).join('') || '<p class="empty-state">No profiles yet.</p>';
    }

    async function syncSession(session) {
        user = session?.user || null;
        profile = null;
        if (user) {
            try {
                await loadProfile();
                setupNotice.hidden = true;
                await refreshData();
            } catch (error) {
                setNotice(error.message || 'Could not load your profile.', 'error');
            }
        } else {
            showConnectedArea();
        }
    }

    async function start() {
        const url = String(config.url || '');
        const anonKey = String(config.anonKey || '');
        if (!window.supabase || !url.startsWith('https://') || url.includes('YOUR_') || !anonKey || anonKey.includes('YOUR_')) {
            setupNotice.hidden = false;
            setNotice('Supabase is not configured yet. Add your project URL and publishable anon key in docs/assets/supabase-config.js.', 'error');
            showConnectedArea();
            return;
        }
        client = window.supabase.createClient(url, anonKey);
        setupNotice.hidden = true;
        setNotice('Connecting to Pig Services…');
        const { data, error } = await client.auth.getSession();
        if (error) setNotice(error.message, 'error');
        await syncSession(data?.session || null);
        if (!error) setNotice('');
        client.auth.onAuthStateChange((_event, session) => { window.setTimeout(() => syncSession(session), 0); });
    }

    document.getElementById('sign-in-button').addEventListener('click', () => openAuth('login'));
    document.getElementById('sign-up-button').addEventListener('click', () => openAuth('signup'));
    document.getElementById('close-auth').addEventListener('click', () => authDialog.close());
    document.getElementById('show-signup').addEventListener('click', () => openAuth('signup'));
    document.getElementById('show-login').addEventListener('click', () => openAuth('login'));
    document.getElementById('sign-out-button').addEventListener('click', async () => { await client.auth.signOut(); setNotice('You have signed out.'); });

    document.getElementById('login-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const { error } = await client.auth.signInWithPassword({ email: form.get('email'), password: form.get('password') });
        setAuthMessage(error ? error.message : 'Signed in.', error ? 'error' : 'success');
        if (!error) authDialog.close();
    });

    document.getElementById('signup-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const { data, error } = await client.auth.signUp({
            email: form.get('email'),
            password: form.get('password'),
            options: { data: { full_name: form.get('full_name'), phone: form.get('phone'), address: form.get('address'), role: form.get('role') } }
        });
        if (error) { setAuthMessage(error.message, 'error'); return; }
        if (data.session) authDialog.close();
        setAuthMessage(data.session ? 'Account created.' : 'Account created. Check your email to confirm it, then sign in.', 'success');
    });

    document.getElementById('listing-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const { error } = await client.from('services').insert({
            owner_id: user.id, service_type: form.get('service_type'), pig_name: form.get('pig_name'),
            breed: form.get('breed'), age_months: Number(form.get('age_months')), location: form.get('location'), description: form.get('description')
        });
        if (error) { setNotice(error.message, 'error'); return; }
        event.currentTarget.reset();
        setNotice('Service listed.', 'success');
        await refreshData();
    });

    document.getElementById('request-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const { data, error } = await client.from('requests').insert({
            service_id: form.get('service_id'), requester_id: user.id, preferred_date: form.get('preferred_date'), notes: form.get('notes') || ''
        }).select('id,service_id,requester_id').single();
        if (error) { setNotice(error.message, 'error'); return; }
        const service = await client.from('services').select('owner_id').eq('id', data.service_id).single();
        if (!service.error && form.get('notes')) {
            await client.from('messages').insert({ request_id: data.id, sender_id: user.id, recipient_id: service.data.owner_id, message: form.get('notes') });
        }
        requestDialog.close();
        setNotice('Request submitted.', 'success');
        await refreshData();
    });

    document.getElementById('close-request').addEventListener('click', () => requestDialog.close());
    document.getElementById('message-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!activeRequest) return;
        const form = new FormData(event.currentTarget);
        const message = String(form.get('message') || '').trim();
        if (!message) return;
        const { error } = await client.from('messages').insert({
            request_id: activeRequest.id, sender_id: user.id,
            recipient_id: document.getElementById('message-recipient-id').value, message
        });
        if (error) { setNotice(error.message, 'error'); return; }
        event.currentTarget.reset();
        await openConversation(activeRequest.id);
    });

    document.getElementById('profile-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const { error } = await client.from('profiles').update({ full_name: form.get('full_name'), phone: form.get('phone'), address: form.get('address') }).eq('id', user.id);
        setNotice(error ? error.message : 'Profile updated.', error ? 'error' : 'success');
        if (!error) await syncSession((await client.auth.getSession()).data.session);
    });

    window.addEventListener('DOMContentLoaded', start);
})();

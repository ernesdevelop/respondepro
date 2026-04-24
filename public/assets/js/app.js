const form = document.getElementById('analysisForm');
const messageInput = document.getElementById('mensaje');
const feedback = document.getElementById('feedback');
const resultPanel = document.getElementById('resultPanel');
const categorySuggestion = document.getElementById('categoriaSugerida');
const toneDetected = document.getElementById('tonoDetectado');
const categorySelect = document.getElementById('categoriaSelect');
const explanation = document.getElementById('explicacionCategoria');
const responsesList = document.getElementById('responsesList');
const analyzeBtn = document.getElementById('analyzeBtn');
const generateBtn = document.getElementById('generateBtn');
const usageCounter = document.getElementById('usageCounter');

let currentMessage = '';

const setLoading = (loading, triggerButton) => {
    triggerButton.disabled = loading;
    triggerButton.textContent = loading
        ? (triggerButton === analyzeBtn ? 'Analizando...' : 'Generando...')
        : (triggerButton === analyzeBtn ? 'Analizar' : 'Generar respuestas');
};

const showFeedback = (message, type = 'info') => {
    feedback.textContent = message;
    feedback.className = `feedback ${type}`;
};

const renderResponses = (responses) => {
    responsesList.innerHTML = '';

    responses.forEach((response, index) => {
        const card = document.createElement('article');
        card.className = 'response-card';

        const title = document.createElement('strong');
        title.textContent = `Respuesta ${index + 1}`;

        const body = document.createElement('p');
        body.textContent = response;

        const copyButton = document.createElement('button');
        copyButton.type = 'button';
        copyButton.className = 'copy-btn';
        copyButton.textContent = 'Copiar';
        copyButton.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(response);
                copyButton.textContent = 'Copiado';
                setTimeout(() => {
                    copyButton.textContent = 'Copiar';
                }, 1200);
            } catch (error) {
                showFeedback('No se pudo copiar la respuesta.', 'error');
            }
        });

        card.append(title, body, copyButton);
        responsesList.appendChild(card);
    });
};

const updateUsage = (usage) => {
    if (!usage || !usageCounter) {
        return;
    }

    usageCounter.textContent = `${usage.used} / ${usage.limit}`;
};

const renderResult = (payload) => {
    categorySuggestion.textContent = payload.categoria_sugerida;
    toneDetected.textContent = payload.tono;
    categorySelect.value = payload.categoria_aplicada;
    explanation.textContent = payload.explicacion;
    renderResponses(payload.respuestas);
    resultPanel.classList.remove('hidden');
    generateBtn.disabled = false;
};

const requestAnalysis = async (category = '') => {
    const mensaje = messageInput.value.trim();

    if (mensaje.length < 6) {
        showFeedback('Escribe un mensaje de al menos 6 caracteres.', 'error');
        return;
    }

    currentMessage = mensaje;
    showFeedback(category ? 'Generando respuestas segun la categoria elegida...' : 'Analizando mensaje...', 'info');

    const triggerButton = category ? generateBtn : analyzeBtn;
    setLoading(true, triggerButton);

    try {
        const response = await fetch(window.RespondePro.analyzeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                mensaje,
                categoria: category
            })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Ocurrio un error al procesar la solicitud.');
        }

        renderResult(result.data);
        updateUsage(result.usage);
        showFeedback(result.message, 'success');
    } catch (error) {
        showFeedback(error.message, 'error');
    } finally {
        setLoading(false, triggerButton);
    }
};

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    await requestAnalysis('');
});

generateBtn.addEventListener('click', async () => {
    if (!currentMessage) {
        currentMessage = messageInput.value.trim();
    }

    await requestAnalysis(categorySelect.value);
});

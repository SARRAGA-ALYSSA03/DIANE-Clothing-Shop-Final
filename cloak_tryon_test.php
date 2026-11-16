
<?php
// DIANE AI Virtual Try-On
// Minimalist black and white theme.
// Uses the existing CLOAK API connection.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DIANE | Virtual Try-On</title>

<style>
:root {
    --bg: #f7f7f7;
    --white: #ffffff;
    --black: #111111;
    --border: #e5e5e5;
    --muted: #777777;
    --soft: #eeeeee;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--bg);
    color: var(--black);
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    -webkit-font-smoothing: antialiased;
}

button, input, select {
    font-family: inherit;
}

.page {
    max-width: 1500px;
    margin: auto;
    padding: 30px 36px 60px;
}

/* HEADER */

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 22px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 45px;
}

.logo {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    color: #111;
    text-decoration: none;
}

.logo-name {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 34px;
    font-weight: 400;
    letter-spacing: 2px;
    line-height: 1;
}

.logo-subtitle {
    font-family: Arial, sans-serif;
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 4px;
    margin-top: 7px;
}

.back {
    color: var(--black);
    text-decoration: none;
    font-size: 12px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.back:hover {
    opacity: .55;
}

/* INTRO */

.intro {
    text-align: center;
    margin-bottom: 38px;
}

.intro h1 {
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    font-size: clamp(32px, 4vw, 48px);
    font-weight: 300;
    letter-spacing: -1.5px;
    margin: 0 0 12px;
}

.intro h1 span {
    font-style: italic;
    font-weight: 400;
}

.intro p {
    font-size: 13px;
    color: var(--muted);
    line-height: 1.7;
    letter-spacing: .3px;
}

/* THREE COLUMNS */

.steps {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    align-items: stretch;
}

.step-card {
    min-width: 0;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 5px;
    padding: 23px;
    display: flex;
    flex-direction: column;
}

.step-heading {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}

.step-number {
    width: 33px;
    height: 33px;
    flex-shrink: 0;
    border: 1px solid var(--black);
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 12px;
}

.step-heading h2 {
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    font-size: 19px;
    font-weight: 500;
    margin: 0 0 5px;
}

.step-heading p {
    margin: 0;
    color: var(--muted);
    font-size: 11px;
    letter-spacing: .2px;
}

/* UPLOAD AREAS */

.upload-box {
    height: 365px;
    position: relative;
    border: 1px solid var(--border);
    background: #fafafa;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    overflow: hidden;
    cursor: pointer;
    transition: border .2s, background .2s;
}

.upload-box:hover {
    border-color: var(--black);
    background: #f4f4f4;
}

.upload-box input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
    z-index: 3;
}

.upload-content {
    padding: 15px;
}

.upload-icon {
    font-size: 32px;
    margin-bottom: 16px;
    color: #555;
}

.upload-title {
    color: #333;
    font-size: 14px;
}

.upload-subtitle {
    color: #999;
    font-size: 11px;
    margin-top: 8px;
}

.preview {
    display: none;
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 8px;
    background: #fafafa;
}

.upload-box.has-image .preview {
    display: block;
}

.upload-box.has-image .upload-content {
    display: none;
}

.upload-footer {
    border: 1px solid var(--border);
    border-top: 0;
    padding: 13px;
    text-align: center;
    color: var(--black);
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* OUTFIT SELECTION */

.outfit-tabs {
    display: flex;
    gap: 7px;
    margin-bottom: 25px;
}

.tab {
    flex: 1;
    padding: 12px 7px;
    border: 1px solid var(--border);
    background: white;
    color: var(--black);
    font-size: 11px;
    letter-spacing: .4px;
    cursor: pointer;
    transition: .2s;
}

.tab:hover {
    border-color: var(--black);
}

.tab.active {
    background: var(--black);
    color: white;
    border-color: var(--black);
}

.field-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 1px;
    margin-bottom: 12px;
    display: block;
}

.garment-field {
    margin-bottom: 18px;
}

.garment-box {
    height: 180px;
}

.garment-box .preview {
    object-fit: contain;
}

.hint {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.6;
    margin-top: 12px;
}

/* RESULT */

.result-box {
    height: 430px;
    border: 1px solid var(--border);
    background: #fafafa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.result-placeholder {
    text-align: center;
    color: #888;
    padding: 20px;
}

.result-placeholder .symbol {
    font-size: 35px;
    margin-bottom: 15px;
}

.result-placeholder strong {
    font-size: 13px;
    font-weight: 500;
    color: #444;
}

.result-placeholder p {
    font-size: 11px;
    line-height: 1.7;
}

.result-image {
    display: none;
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 8px;
}

.status {
    font-size: 11px;
    line-height: 1.6;
    color: var(--muted);
    margin: 13px 0;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.status.error {
    color: #b42318;
}

.status.success {
    color: #267344;
}

/* BUTTONS */

.generate-button {
    width: 100%;
    padding: 16px;
    border: 1px solid var(--black);
    background: var(--black);
    color: white;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 2px;
    cursor: pointer;
    transition: .2s;
    margin-top: auto;
}

.generate-button:hover {
    background: #333;
}

.generate-button:disabled {
    opacity: .5;
    cursor: wait;
}

.result-actions {
    display: flex;
    gap: 9px;
    margin-top: 12px;
}

.secondary-button {
    flex: 1;
    border: 1px solid var(--border);
    background: white;
    color: var(--black);
    padding: 12px 7px;
    font-size: 10px;
    letter-spacing: .7px;
    text-transform: uppercase;
    cursor: pointer;
}

.secondary-button:hover {
    border-color: var(--black);
}

/* FOOTER */

.footer {
    text-align: center;
    margin-top: 45px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
    color: #888;
    font-size: 10px;
    letter-spacing: 2px;
}

/* RESPONSIVE */

@media (max-width: 1000px) {
    .steps {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .result-card {
        grid-column: 1 / -1;
    }

    .result-box {
        height: 500px;
    }
}

@media (max-width: 650px) {
    .page {
        padding: 18px 13px 35px;
    }

    .topbar {
        margin-bottom: 30px;
        gap: 16px;
    }

    .logo-name {
        font-size: 27px;
    }

    .logo-subtitle {
        font-size: 7px;
        letter-spacing: 3.5px;
    }

    .back {
        font-size: 10px;
    }

    .intro {
        margin-bottom: 25px;
    }

    .intro h1 {
        font-size: 32px;
    }

    .steps {
        grid-template-columns: 1fr;
    }

    .result-card {
        grid-column: auto;
    }

    .step-card {
        padding: 17px;
    }

    .upload-box {
        height: 340px;
    }

    .garment-box {
        height: 190px;
    }

    .result-box {
        height: 430px;
    }
}
</style>
</head>

<body>

<main class="page">

    <!-- HEADER -->
    <header class="topbar">
        <a class="logo" href="index.php" aria-label="DIANE Clothing Shop home">
    <span class="logo-name">DIANE</span>
    <span class="logo-subtitle">CLOTHING SHOP</span>
    </div>
        <a class="back" href="index.php">← Back to DIANE</a>
    </header>

    <!-- INTRO -->
    <section class="intro">
        <h1>Try it on. <span>See it on you.</span></h1>
        <p>
            Discover your favorite DIANE pieces before you buy.
            Experience your look with AI Virtual Try-On.
        </p>
    </section>

    <form id="testForm" enctype="multipart/form-data">

        <div class="steps">

            <!-- STEP 1 -->
            <section class="step-card">

                <div class="step-heading">
                    <div class="step-number">01</div>
                    <div>
                        <h2>Your Photo</h2>
                        <p>Upload your photo or take one</p>
                    </div>
                </div>

                <label class="upload-box" id="personBox" for="person">

                    <div class="upload-content">
                        <div class="upload-icon">＋</div>
                        <div class="upload-title">
                            Upload your photo
                        </div>
                        <div class="upload-subtitle">
                            JPG, PNG or WEBP · Max 12 MB
                        </div>
                    </div>

                    <img
                        class="preview"
                        id="personPreview"
                        alt="Customer photo preview"
                    >

                    <input
                        id="person"
                        name="person"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        capture="user"
                        required
                    >

                </label>

                <div class="upload-footer">
                    ↑ &nbsp; Choose your photo
                </div>

                <p class="hint">
                    Use a clear, full-body photo with good lighting
                    for better results.
                </p>

            </section>


            <!-- STEP 2 -->
            <section class="step-card">

                <div class="step-heading">
                    <div class="step-number">02</div>
                    <div>
                        <h2>Select Your Item</h2>
                        <p>Choose a DIANE clothing category</p>
                    </div>
                </div>

                <div class="outfit-tabs">

                    <button
                        type="button"
                        class="tab active"
                        data-type="top"
                    >TOP</button>

                    <button
                        type="button"
                        class="tab"
                        data-type="bottom"
                    >BOTTOM</button>

                    <button
                        type="button"
                        class="tab"
                        data-type="dress"
                    >DRESS</button>

                </div>

                <input
                    type="hidden"
                    id="garment_type"
                    name="garment_type"
                    value="top"
                >

                <div class="garment-field">

                    <label
                        class="field-label"
                        id="garmentLabel"
                        for="garment"
                    >
                        TOP / BLOUSE / SHIRT
                    </label>

                    <label
                        class="upload-box garment-box"
                        id="garmentBox"
                        for="garment"
                    >

                        <div class="upload-content">
                            <div class="upload-icon">＋</div>
                            <div class="upload-title" id="garmentTitle">
                                Select a DIANE product
                            </div>
                            <div class="upload-subtitle">
                                Upload the product image
                            </div>
                        </div>

                        <img
                            class="preview"
                            id="garmentPreview"
                            alt="Selected garment preview"
                        >

                        <input
                            id="garment"
                            name="garment"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            required
                        >

                    </label>

                    <div class="upload-footer">
                        ↑ &nbsp; Choose product image
                    </div>

                </div>

                <p class="hint">
                    Select the clothing image you want to try on.
                </p>

            </section>


            <!-- STEP 3 -->
            <section class="step-card result-card">

                <div class="step-heading">
                    <div class="step-number">03</div>
                    <div>
                        <h2>Your Look</h2>
                        <p>Your AI-generated result</p>
                    </div>
                </div>

                <div class="result-box">

                    <div
                        class="result-placeholder"
                        id="resultPlaceholder"
                    >
                        <div class="symbol">✧</div>
                        <strong>Your look will appear here</strong>
                        <p>
                            Upload your photo and select a DIANE item
                            to see your AI-generated look.
                        </p>
                    </div>

                    <img
                        id="result"
                        class="result-image"
                        alt="Generated AI Try-On result"
                    >

                </div>

                <p id="status" class="status" role="status">
                    Your generated result will appear here.
                </p>

                <button
                    id="generate"
                    class="generate-button"
                    type="submit"
                >
                    GENERATE AI TRY-ON
                </button>

                <div class="result-actions">

                    <button
                        type="button"
                        class="secondary-button"
                        id="download"
                    >
                        SAVE RESULT
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        id="reset"
                    >
                        TRY ANOTHER
                    </button>

                </div>

            </section>

        </div>

    </form>

    <footer class="footer">
        DIANE CLOTHING SHOP · VIRTUAL TRY-ON
    </footer>

</main>


<script>
const form = document.getElementById('testForm');
const button = document.getElementById('generate');
const statusEl = document.getElementById('status');

const result = document.getElementById('result');
const placeholder = document.getElementById('resultPlaceholder');

const personInput = document.getElementById('person');
const garmentInput = document.getElementById('garment');

const personBox = document.getElementById('personBox');
const garmentBox = document.getElementById('garmentBox');

const personPreview = document.getElementById('personPreview');
const garmentPreview = document.getElementById('garmentPreview');

const garmentType = document.getElementById('garment_type');
const garmentLabel = document.getElementById('garmentLabel');
const garmentTitle = document.getElementById('garmentTitle');

let generatedResultUrl = '';
let generatedResultBlob = null;
let personFile = null;
let garmentFile = null;

const DB_NAME = 'diane-ai-tryon';
const DB_VERSION = 1;
const STORE_NAME = 'session';

function openTryOnDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME);
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function saveState(key, value) {
    const db = await openTryOnDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).put(value, key);
        tx.oncomplete = () => { db.close(); resolve(); };
        tx.onerror = () => { db.close(); reject(tx.error); };
    });
}

async function readState(key) {
    const db = await openTryOnDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readonly');
        const request = tx.objectStore(STORE_NAME).get(key);
        request.onsuccess = () => { db.close(); resolve(request.result); };
        request.onerror = () => { db.close(); reject(request.error); };
    });
}

async function clearState() {
    const db = await openTryOnDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).clear();
        tx.oncomplete = () => { db.close(); resolve(); };
        tx.onerror = () => { db.close(); reject(tx.error); };
    });
}

function showPreview(file, preview, box) {
    if (!file) return;
    const objectUrl = URL.createObjectURL(file);
    preview.src = objectUrl;
    box.classList.add('has-image');
}

function validateImage(file) {
    if (!file) return false;
    if (file.size > 12 * 1024 * 1024) {
        alert('Please choose an image smaller than 12 MB.');
        return false;
    }
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        alert('Please choose JPG, PNG or WEBP.');
        return false;
    }
    return true;
}

async function rememberUpload(key, file) {
    if (!file) return;
    await saveState(key, {
        blob: file,
        name: file.name,
        type: file.type,
        lastModified: file.lastModified
    });
}

function restoreFile(record) {
    if (!record || !record.blob) return null;
    return new File([record.blob], record.name || 'upload-image', {
        type: record.type || record.blob.type || 'image/jpeg',
        lastModified: record.lastModified || Date.now()
    });
}

personInput.addEventListener('change', async () => {
    const file = personInput.files[0];
    if (!validateImage(file)) {
        personInput.value = '';
        return;
    }
    personFile = file;
    showPreview(file, personPreview, personBox);
    try {
        await rememberUpload('person', file);
    } catch (error) {
        console.warn('Could not save customer photo for refresh recovery:', error);
    }
});

garmentInput.addEventListener('change', async () => {
    const file = garmentInput.files[0];
    if (!validateImage(file)) {
        garmentInput.value = '';
        return;
    }
    garmentFile = file;
    showPreview(file, garmentPreview, garmentBox);
    try {
        await rememberUpload('garment', file);
    } catch (error) {
        console.warn('Could not save product photo for refresh recovery:', error);
    }
});

// CATEGORY TABS
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', async () => {
        document.querySelectorAll('.tab').forEach(item => item.classList.remove('active'));
        tab.classList.add('active');

        const type = tab.dataset.type;
        garmentType.value = type;

        const labels = {
            top: { label: 'TOP / BLOUSE / SHIRT', title: 'Select a DIANE top' },
            bottom: { label: 'BOTTOM / PANTS / SKIRT', title: 'Select DIANE pants or skirt' },
            dress: { label: 'DRESS / GOWN', title: 'Select a DIANE dress' }
        };

        garmentLabel.textContent = labels[type].label;
        garmentTitle.textContent = labels[type].title;

        garmentInput.value = '';
        garmentFile = null;
        garmentPreview.removeAttribute('src');
        garmentBox.classList.remove('has-image');

        try {
            const db = await openTryOnDB();
            await new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_NAME, 'readwrite');
                tx.objectStore(STORE_NAME).delete('garment');
                tx.oncomplete = resolve;
                tx.onerror = () => reject(tx.error);
            });
            db.close();
        } catch (error) {
            console.warn('Could not clear previous product image:', error);
        }
    });
});

// GENERATE AI TRY-ON
form.addEventListener('submit', async e => {
    e.preventDefault();

    const customerPhoto = personInput.files[0] || personFile;
    const productPhoto = garmentInput.files[0] || garmentFile;

    if (!customerPhoto || !productPhoto) {
        alert('Please upload your photo and a DIANE product image.');
        return;
    }

    button.disabled = true;
    button.textContent = 'GENERATING...';
    statusEl.className = 'status';
    statusEl.textContent =
        'Uploading your images to CLOAK. Please wait while AI generates your look.';

    result.style.display = 'none';
    placeholder.style.display = 'none';
    generatedResultUrl = '';
    generatedResultBlob = null;

    try {
        const payload = new FormData();
        payload.append('person', customerPhoto, customerPhoto.name || 'customer-photo.jpg');
        payload.append('garment', productPhoto, productPhoto.name || 'diane-product.jpg');
        payload.append('garment_type', garmentType.value);

        const response = await fetch('cloak_tryon_api.php', {
            method: 'POST',
            body: payload
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'AI generation failed.');
        }
        if (!data.result_url) {
            throw new Error('The AI response did not include a result image.');
        }

        generatedResultUrl = data.result_url;
        result.onload = () => {
            result.style.display = 'block';
            placeholder.style.display = 'none';
        };
        result.onerror = () => {
            statusEl.className = 'status error';
            statusEl.textContent =
                'The result was generated, but its image could not be displayed. Please generate again.';
        };
        result.src = data.result_url;

        // Save the generated image itself where browser permissions allow it,
        // so the result can be restored after a page refresh.
        try {
            const imageResponse = await fetch(data.result_url);
            if (imageResponse.ok) {
                generatedResultBlob = await imageResponse.blob();
                await saveState('result', {
                    blob: generatedResultBlob,
                    type: generatedResultBlob.type || 'image/png',
                    url: data.result_url
                });
            } else {
                await saveState('result-url', data.result_url);
            }
        } catch (storageError) {
            // Keep the result URL as a best-effort fallback; temporary CLOAK
            // URLs may expire when the Colab session stops.
            try { await saveState('result-url', data.result_url); } catch (_) {}
        }

        statusEl.className = 'status success';
        statusEl.textContent =
            data.message || 'Your DIANE AI Try-On result is ready!';
    } catch (err) {
        placeholder.style.display = 'block';
        statusEl.className = 'status error';
        statusEl.textContent = 'AI Try-On failed: ' + err.message;
    } finally {
        button.disabled = false;
        button.textContent = 'GENERATE AI TRY-ON';
    }
});

// SAVE RESULT
document.getElementById('download').addEventListener('click', async () => {
    if (!generatedResultUrl && !generatedResultBlob) {
        alert('Generate your AI Try-On result first.');
        return;
    }

    try {
        let blob = generatedResultBlob;
        if (!blob && generatedResultUrl) {
            const response = await fetch(generatedResultUrl);
            if (!response.ok) throw new Error('Could not retrieve the result image.');
            blob = await response.blob();
        }

        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = 'DIANE-AI-Try-On.png';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    } catch (err) {
        if (generatedResultUrl) {
            window.open(generatedResultUrl, '_blank', 'noopener');
        } else {
            alert('Unable to save the image. Please try generating it again.');
        }
    }
});

// TRY ANOTHER clears the current try-on session and returns to TOP.
document.getElementById('reset').addEventListener('click', async () => {
    form.reset();
    personFile = null;
    garmentFile = null;
    generatedResultBlob = null;
    generatedResultUrl = '';

    personPreview.removeAttribute('src');
    garmentPreview.removeAttribute('src');
    result.removeAttribute('src');

    personBox.classList.remove('has-image');
    garmentBox.classList.remove('has-image');

    result.style.display = 'none';
    placeholder.style.display = 'block';

    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.type === 'top');
    });

    garmentType.value = 'top';
    garmentLabel.textContent = 'TOP / BLOUSE / SHIRT';
    garmentTitle.textContent = 'Select a DIANE top';

    statusEl.className = 'status';
    statusEl.textContent = 'Your generated result will appear here.';
    button.disabled = false;
    button.textContent = 'GENERATE AI TRY-ON';

    try {
        await clearState();
    } catch (error) {
        console.warn('Could not clear saved try-on session:', error);
    }
});

// Restore the current try-on after refresh.
(async function restoreTryOnSession() {
    try {
        const [savedPerson, savedGarment, savedResult, savedResultUrl] = await Promise.all([
            readState('person'),
            readState('garment'),
            readState('result'),
            readState('result-url')
        ]);

        if (savedPerson) {
            personFile = restoreFile(savedPerson);
            showPreview(personFile, personPreview, personBox);
        }
        if (savedGarment) {
            garmentFile = restoreFile(savedGarment);
            showPreview(garmentFile, garmentPreview, garmentBox);
        }

        if (savedResult && savedResult.blob) {
            generatedResultBlob = savedResult.blob;
            generatedResultUrl = savedResult.url || '';
            const objectUrl = URL.createObjectURL(savedResult.blob);
            result.onload = () => {
                result.style.display = 'block';
                placeholder.style.display = 'none';
            };
            result.src = objectUrl;
            statusEl.className = 'status success';
            statusEl.textContent = 'Your previous DIANE AI Try-On result has been restored.';
        } else if (savedResultUrl) {
            generatedResultUrl = savedResultUrl;
            result.onload = () => {
                result.style.display = 'block';
                placeholder.style.display = 'none';
            };
            result.onerror = () => {
                statusEl.className = 'status';
                statusEl.textContent =
                    'Your previous result link has expired. Please generate your look again.';
            };
            result.src = savedResultUrl;
        }

        if (personFile || garmentFile) {
            statusEl.className = 'status';
            if (!savedResult && !savedResultUrl) {
                statusEl.textContent =
                    'Your uploaded images were restored. You can continue your AI Try-On.';
            }
        }
    } catch (error) {
        console.warn('Could not restore the previous try-on session:', error);
    }
})();
</script>

</body>
</html>

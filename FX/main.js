// ====================================================================
// 1. GLOBAL VARIABLES AND CONFIGURATION
// ====================================================================

let scene, camera, renderer, particles;
let clock = new THREE.Clock();

const PARTICLE_COUNT = 50000;
const particleGeometry = new THREE.BufferGeometry();
// Buffers for Three.js attributes
let positions = new Float32Array(PARTICLE_COUNT * 3);
let colors = new Float32Array(PARTICLE_COUNT * 3);
let sizes = new Float32Array(PARTICLE_COUNT);
let lifeTimes = new Float32Array(PARTICLE_COUNT);

// State variables driven by MediaPipe
let handPosition = new THREE.Vector3(0, 0, 0); // Normalized -1 to 1 (X, Y)
let gestureType = 'IDLE'; 
let particleTemplate = 'FIREWORKS'; // Current template

// Gesture-to-Action Mapping
const TEMPLATES = {
    FIREWORKS: { name: "Fireworks", size: 30, color: new THREE.Color(0xFF8800), spread: 2.0 },
    HEARTS: { name: "Hearts", size: 15, color: new THREE.Color(0xFF00FF), spread: 1.0 },
    SATURN: { name: "Saturn", size: 5, color: new THREE.Color(0xAAAAFF), spread: 0.5 },
};

// ====================================================================
// 2. HELPER FUNCTIONS
// ====================================================================

function onWindowResize() {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
}

function switchTemplate(newTemplate) {
    if (particleTemplate !== newTemplate && TEMPLATES[newTemplate]) {
        particleTemplate = newTemplate;
        console.log(`Template Switched to: ${TEMPLATES[newTemplate].name}`);
        // Add visual feedback here (e.g., flash the screen, play a sound)
    }
}

// ====================================================================
// 3. CORE THREE.JS INITIALIZATION
// ====================================================================

function createParticles() {
    const template = TEMPLATES[particleTemplate];

    for (let i = 0; i < PARTICLE_COUNT; i++) {
        // Initial spread around the center
        positions[i * 3 + 0] = (Math.random() - 0.5) * 10;
        positions[i * 3 + 1] = (Math.random() - 0.5) * 10;
        positions[i * 3 + 2] = (Math.random() - 0.5) * 10;
        
        // Initial attributes
        colors[i * 3 + 0] = template.color.r;
        colors[i * 3 + 1] = template.color.g;
        colors[i * 3 + 2] = template.color.b;

        sizes[i] = template.size * Math.random();
        lifeTimes[i] = Math.random() * 5; 
    }

    particleGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    particleGeometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
    particleGeometry.setAttribute('size', new THREE.BufferAttribute(sizes, 1)); // We won't use this with PointsMaterial, but good practice
    particleGeometry.setAttribute('lifeTime', new THREE.BufferAttribute(lifeTimes, 1));
    
    // Simple PointsMaterial - for better scaling control, use ShaderMaterial
    const particleMaterial = new THREE.PointsMaterial({
        size: 0.1, 
        vertexColors: true,
        transparent: true,
        opacity: 0.8,
        blending: THREE.AdditiveBlending,
    });

    particles = new THREE.Points(particleGeometry, particleMaterial);
    scene.add(particles);
}

function init() {
    // Setup Scene, Camera, Renderer
    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.z = 5;

    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    document.body.appendChild(renderer.domElement);
    
    createParticles(); 
    setupMediaPipe(); 
    window.addEventListener('resize', onWindowResize, false);
}

// ====================================================================
// 4. MEDIAPIPE HAND TRACKING SETUP
// ====================================================================

function setupMediaPipe() {
    const videoElement = document.getElementById('webcam-input');
    
    // MediaPipe Hands setup
    const hands = new Hands({
        locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`
    });

    hands.setOptions({
        maxNumHands: 1,
        modelComplexity: 1,
        minDetectionConfidence: 0.7,
        minTrackingConfidence: 0.7
    });

    hands.onResults(onResults);

    // Camera utility to handle webcam stream
    const camera = new Camera(videoElement, {
        onFrame: async () => {
            await hands.send({ image: videoElement });
        },
        width: 640,
        height: 480
    });
    camera.start();
}

function onResults(results) {
    if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
        const landmarks = results.multiHandLandmarks[0];
        const landmark = landmarks[9]; // Middle finger base

        // 1. Update Hand Position
        // Convert normalized (0-1) to Three.js world space (approximated)
        handPosition.x = (landmark.x * 2 - 1) * -5; // Range approx -5 to 5, flipped X
        handPosition.y = (landmark.y * 2 - 1) * -5; // Range approx -5 to 5, inverted Y
        handPosition.z = landmark.z * -10 + 3; // Z depth approximation

        // 2. Determine Gesture Type (Simplified Logic)
        let fingersUp = 0;
        // Check Index (8), Middle (12), Ring (16), Pinky (20) tips vs joints (2 less)
        for (let i of [8, 12, 16, 20]) {
            if (landmarks[i].y < landmarks[i - 2].y) {
                fingersUp++;
            }
        }
        
        // Gesture Logic Mapping
        if (fingersUp >= 3) {
            gestureType = 'OPEN_HAND';
            switchTemplate('FIREWORKS');
        } else if (fingersUp === 0) {
            gestureType = 'FIST';
            switchTemplate('SATURN');
        } else if (fingersUp === 2 && landmarks[8].y < landmarks[12].y) {
            gestureType = 'V_SIGN';
            switchTemplate('HEARTS'); 
        } else {
            gestureType = 'IDLE';
        }
        
    } else {
        gestureType = 'NONE';
    }
}

// ====================================================================
// 5. ANIMATION LOOP AND PARTICLE UPDATE
// ====================================================================

function updateParticles(delta) {
    const template = TEMPLATES[particleTemplate];
    const positions = particleGeometry.attributes.position.array;
    const colors = particleGeometry.attributes.color.array;
    
    // Hand World Position
    const hX = handPosition.x;
    const hY = handPosition.y;
    const hZ = handPosition.z;
    
    // Material Size Update based on Gesture (e.g., open hand expands size)
    const baseSize = template.size;
    const currentSize = particles.material.size;
    
    if (gestureType === 'OPEN_HAND') {
         // Smoothly increase size
         particles.material.size = currentSize + (baseSize * 0.05 - currentSize) * delta * 5; 
    } else {
         // Smoothly decrease size back to default
         particles.material.size = currentSize + (baseSize * 0.01 - currentSize) * delta * 5; 
    }


    for (let i = 0; i < PARTICLE_COUNT; i++) {
        const i3 = i * 3;
        
        // --- Particle Dynamics (Attraction and Emission) ---
        const dx = hX - positions[i3];
        const dy = hY - positions[i3 + 1];
        const dz = hZ - positions[i3 + 2];
        
        // Simple attraction/velocity towards the hand
        const speedMultiplier = (gestureType === 'FIST' ? 5.0 : 1.5); 
        positions[i3] += dx * delta * speedMultiplier;
        positions[i3 + 1] += dy * delta * speedMultiplier;
        positions[i3 + 2] += dz * delta * speedMultiplier;

        // --- Color Update (Lerp to Template Color) ---
        colors[i3] = template.color.r;
        colors[i3 + 1] = template.color.g;
        colors[i3 + 2] = template.color.b;

        // --- Lifetime/Respawn Logic (Emitters) ---
        lifeTimes[i] -= delta;
        if (lifeTimes[i] < 0) {
            // Respawn particle near the hand position
            positions[i3] = hX + (Math.random() - 0.5) * template.spread;
            positions[i3 + 1] = hY + (Math.random() - 0.5) * template.spread;
            positions[i3 + 2] = hZ + (Math.random() - 0.5) * template.spread;
            lifeTimes[i] = 1 + Math.random() * 2; // New lifetime
        }
    }

    // Must be called to push updated CPU buffer data to the GPU
    particleGeometry.attributes.position.needsUpdate = true;
    particleGeometry.attributes.color.needsUpdate = true;
}

function animate() {
    requestAnimationFrame(animate);
    const delta = clock.getDelta();
    
    updateParticles(delta);

    renderer.render(scene, camera);
}

// ====================================================================
// 6. EXECUTION
// ====================================================================

init();
animate();
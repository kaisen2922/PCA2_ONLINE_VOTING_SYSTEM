/**
 * ApexVote – Kolkata College Network 3D Rubik's Cube Engine
 * Built with Three.js WebGL & Vanilla JavaScript
 * 
 * Features:
 * - 3x3x3 Physical Rubik's Cube with 54 exterior institution tiles
 * - Procedural crisp canvas textures with college abbreviations and typography
 * - Smooth pointer/touch dragging with momentum damping
 * - Raycaster tile hover detection with golden emissive glow
 * - Dynamic tooltip and college information popover
 * - Auto-rotation drift, Shuffle animation, and Reset view controls
 * - Visibility detection & IntersectionObserver performance pausing
 * - Graceful fallback support for systems without WebGL
 */

(function () {
    'use strict';

    class KolkataRubikCube {
        constructor(options = {}) {
            this.container = document.getElementById(options.containerId || 'kolkataCubeContainer');
            this.wrapper = document.getElementById(options.wrapperId || 'cubeStageWrapper');
            this.tooltip = document.getElementById(options.tooltipId || 'cubeTileTooltip');
            this.fallbackView = document.getElementById(options.fallbackId || 'cubeFallbackView');
            
            if (!this.container) return;

            // State
            this.isRunning = false;
            this.isAutoRotating = true;
            this.isDragging = false;
            this.hasInteracted = false;
            this.idleTimer = null;
            this.pointerDownPos = { x: 0, y: 0 };
            this.previousPointerPos = { x: 0, y: 0 };
            this.angularVelocity = { x: 0, y: 0 };
            
            // Animation States
            this.isAnimatingView = false;
            this.viewAnimationProgress = 0;
            this.viewStartRot = { x: 0, y: 0, z: 0 };
            this.viewTargetRot = { x: -0.32, y: 0.62, z: 0 };

            this.tiles = [];
            this.hoveredTile = null;
            this.raycaster = new THREE.Raycaster();
            this.mouse = new THREE.Vector2(-1000, -1000);

            // Palette tokens
            this.colors = {
                bg: 0x241B16,
                cubieCore: 0x1A130E,
                faces: [
                    { bg: '#5A4332', text: '#F6EEDC', name: 'Caramel Walnut' },    // Face 0: +X (Right)
                    { bg: '#F6EEDC', text: '#241B16', name: 'Warm Cream' },       // Face 1: -X (Left)
                    { bg: '#F4CB45', text: '#241B16', name: 'Mustard Gold' },     // Face 2: +Y (Top)
                    { bg: '#30251E', text: '#F4CB45', name: 'Deep Chocolate' },   // Face 3: -Y (Bottom)
                    { bg: '#DDB63F', text: '#241B16', name: 'Muted Gold' },       // Face 4: +Z (Front)
                    { bg: '#C7B69F', text: '#241B16', name: 'Soft Sand' }         // Face 5: -Z (Back)
                ]
            };

            this.init();
        }

        init() {
            // 1. Verify Three.js and WebGL Availability
            if (typeof THREE === 'undefined' || !this.isWebGLAvailable()) {
                this.activateFallback();
                return;
            }

            try {
                this.initScene();
                this.initLights();
                this.initCube();
                this.initEvents();
                this.initUIControls();
                this.initObserver();

                // Start Render Loop
                this.isRunning = true;
                this.animate();

                // Initial Entrance Animation
                this.playEntranceAnimation();
            } catch (err) {
                console.warn('Three.js initialization notice:', err);
                this.activateFallback();
            }
        }

        isWebGLAvailable() {
            try {
                const canvas = document.createElement('canvas');
                return !!(window.WebGLRenderingContext && (canvas.getContext('webgl') || canvas.getContext('experimental-webgl')));
            } catch (e) {
                return false;
            }
        }

        activateFallback() {
            if (this.fallbackView) {
                this.fallbackView.classList.add('is-active');
            }
            if (this.container) {
                this.container.style.display = 'none';
            }
            const statusPill = document.querySelector('.cube-status-pill');
            if (statusPill) {
                statusPill.innerHTML = '<span class="cube-status-indicator" style="background:#E8B84A;"></span> Interactive CSS View';
            }
        }

        initScene() {
            const width = this.container.clientWidth || 800;
            const height = this.container.clientHeight || 520;

            this.scene = new THREE.Scene();
            
            // Perspective Camera
            this.camera = new THREE.PerspectiveCamera(40, width / height, 0.1, 100);
            this.camera.position.set(0, 0, 9.2);

            // WebGL Renderer with Alpha transparency
            this.renderer = new THREE.WebGLRenderer({
                antialias: true,
                alpha: true,
                powerPreference: 'high-performance'
            });
            this.renderer.setSize(width, height);
            this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
            this.renderer.setClearColor(0x000000, 0); // Transparent canvas

            this.container.appendChild(this.renderer.domElement);

            // Parent Group for 3D rotation
            this.cubeGroup = new THREE.Group();
            this.cubeGroup.rotation.x = this.viewTargetRot.x;
            this.cubeGroup.rotation.y = this.viewTargetRot.y;
            this.scene.add(this.cubeGroup);

            // Contact Shadow Plane beneath cube
            this.initContactShadow();
        }

        initLights() {
            // Warm Ambient Light
            const ambient = new THREE.AmbientLight(0xFFF5E6, 1.1);
            this.scene.add(ambient);

            // Key Light (Top-Right Front)
            const keyLight = new THREE.DirectionalLight(0xFFF2D6, 1.8);
            keyLight.position.set(6, 8, 8);
            this.scene.add(keyLight);

            // Fill Light (Bottom-Left Back)
            const fillLight = new THREE.DirectionalLight(0xC0A68A, 0.85);
            fillLight.position.set(-6, -4, -6);
            this.scene.add(fillLight);

            // Golden Accent Rim Light
            const rimLight = new THREE.DirectionalLight(0xF4CB45, 0.6);
            rimLight.position.set(0, -6, 6);
            this.scene.add(rimLight);
        }

        initContactShadow() {
            const canvas = document.createElement('canvas');
            canvas.width = 256;
            canvas.height = 256;
            const ctx = canvas.getContext('2d');

            const gradient = ctx.createRadialGradient(128, 128, 0, 128, 128, 128);
            gradient.addColorStop(0, 'rgba(18, 12, 8, 0.65)');
            gradient.addColorStop(0.4, 'rgba(18, 12, 8, 0.35)');
            gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');

            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, 256, 256);

            const shadowTexture = new THREE.CanvasTexture(canvas);
            const shadowGeo = new THREE.PlaneGeometry(6.8, 6.8);
            const shadowMat = new THREE.MeshBasicMaterial({
                map: shadowTexture,
                transparent: true,
                depthWrite: false
            });

            const shadowMesh = new THREE.Mesh(shadowGeo, shadowMat);
            shadowMesh.rotation.x = -Math.PI / 2;
            shadowMesh.position.y = -2.45;
            this.scene.add(shadowMesh);
        }

        initCube() {
            // Get Curated 54-tile distribution of Kolkata institutions
            const distribution = window.KolkataCollegeDirectory ? 
                window.KolkataCollegeDirectory.getTilesDistribution() : [];

            const step = 1.05; // Spacing between cubie centers
            const cubieSize = 0.96;
            const tileOffset = cubieSize / 2 + 0.012; // Position sticker slightly proud of cubie
            const tileGeometry = new THREE.PlaneGeometry(0.88, 0.88);

            // Shared Dark Matte Cubie Core Material
            const coreMaterial = new THREE.MeshStandardMaterial({
                color: this.colors.cubieCore,
                roughness: 0.75,
                metalness: 0.15
            });

            const coreGeometry = new THREE.BoxGeometry(cubieSize, cubieSize, cubieSize);

            let tileCount = 0;

            // Iterate 3x3x3 grid (-1, 0, 1 on X, Y, Z)
            for (let x = -1; x <= 1; x++) {
                for (let y = -1; y <= 1; y++) {
                    for (let z = -1; z <= 1; z++) {
                        // Skip the internal invisible center core (0,0,0) for performance
                        if (x === 0 && y === 0 && z === 0) continue;

                        const cubieMesh = new THREE.Mesh(coreGeometry, coreMaterial);
                        cubieMesh.position.set(x * step, y * step, z * step);
                        this.cubeGroup.add(cubieMesh);

                        // Attach Exterior Tiles to Outward-Facing Sides
                        // 1. Right Face (+X, x === 1) -> Face 0
                        if (x === 1) {
                            const data = distribution[tileCount++] || {};
                            this.addTile(cubieMesh, 0, new THREE.Vector3(tileOffset, 0, 0), new THREE.Euler(0, Math.PI / 2, 0), data, tileGeometry);
                        }
                        // 2. Left Face (-X, x === -1) -> Face 1
                        if (x === -1) {
                            const data = distribution[tileCount++] || {};
                            this.addTile(cubieMesh, 1, new THREE.Vector3(-tileOffset, 0, 0), new THREE.Euler(0, -Math.PI / 2, 0), data, tileGeometry);
                        }
                        // 3. Top Face (+Y, y === 1) -> Face 2
                        if (y === 1) {
                            const data = distribution[tileCount++] || {};
                            this.addTile(cubieMesh, 2, new THREE.Vector3(0, tileOffset, 0), new THREE.Euler(-Math.PI / 2, 0, 0), data, tileGeometry);
                        }
                        // 4. Bottom Face (-Y, y === -1) -> Face 3
                        if (y === -1) {
                            const data = distribution[tileCount++] || {};
                            this.addTile(cubieMesh, 3, new THREE.Vector3(0, -tileOffset, 0), new THREE.Euler(Math.PI / 2, 0, 0), data, tileGeometry);
                        }
                        // 5. Front Face (+Z, z === 1) -> Face 4
                        if (z === 1) {
                            const data = distribution[tileCount++] || {};
                            this.addTile(cubieMesh, 4, new THREE.Vector3(0, 0, tileOffset), new THREE.Euler(0, 0, 0), data, tileGeometry);
                        }
                        // 6. Back Face (-Z, z === -1) -> Face 5
                        if (z === -1) {
                            const data = distribution[tileCount++] || {};
                            this.addTile(cubieMesh, 5, new THREE.Vector3(0, 0, -tileOffset), new THREE.Euler(0, Math.PI, 0), data, tileGeometry);
                        }
                    }
                }
            }
        }

        addTile(parentCubie, faceIndex, localPos, localRot, tileData, geometry) {
            const faceConfig = this.colors.faces[faceIndex % this.colors.faces.length];
            const college = tileData.college || { shortName: 'CU', fullName: 'University of Calcutta' };

            // Generate crisp dynamic texture
            const texture = this.createTileTexture(college.shortName, faceConfig.bg, faceConfig.text);

            const material = new THREE.MeshStandardMaterial({
                map: texture,
                roughness: 0.35,
                metalness: 0.08,
                emissive: 0x000000
            });

            const tileMesh = new THREE.Mesh(geometry, material);
            tileMesh.position.copy(localPos);
            tileMesh.rotation.copy(localRot);

            // Store rich metadata for interaction & raycasting
            tileMesh.userData = {
                isCollegeTile: true,
                college: college,
                faceName: faceConfig.name,
                defaultEmissive: 0x000000
            };

            parentCubie.add(tileMesh);
            this.tiles.push(tileMesh);
        }

        createTileTexture(abbreviation, bgColor, textColor) {
            const canvas = document.createElement('canvas');
            canvas.width = 512;
            canvas.height = 512;
            const ctx = canvas.getContext('2d');

            // 1. Base Tile Background
            ctx.fillStyle = bgColor;
            ctx.beginPath();
            const r = 36; // Rounded corner radius
            ctx.roundRect(14, 14, 484, 484, r);
            ctx.fill();

            // 2. Subtle Tactile Acrylic Bevel Border
            ctx.lineWidth = 14;
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.16)';
            ctx.stroke();

            // Inner Hairline Gloss Highlight
            ctx.lineWidth = 4;
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.22)';
            ctx.beginPath();
            ctx.roundRect(24, 24, 464, 464, r - 8);
            ctx.stroke();

            // 3. College Abbreviation (Centerpiece)
            ctx.fillStyle = textColor;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            // Adaptive Font Size based on abbreviation character length
            const text = String(abbreviation || 'CU').trim();
            let fontSize = 148;
            if (text.length === 3) fontSize = 120;
            if (text.length === 4) fontSize = 104;
            if (text.length >= 5) fontSize = 84;

            ctx.font = `800 ${fontSize}px "Plus Jakarta Sans", "Inter", sans-serif`;
            ctx.fillText(text, 256, 236);

            // 4. Subtle Architectural Baseline Label
            ctx.font = `700 28px "JetBrains Mono", monospace`;
            ctx.fillStyle = (textColor === '#241B16') ? 'rgba(36, 27, 22, 0.65)' : 'rgba(246, 238, 220, 0.7)';
            ctx.letterSpacing = '4px';
            ctx.fillText('KOLKATA', 256, 356);

            // Create Three.js Texture with Mipmapping
            const texture = new THREE.CanvasTexture(canvas);
            texture.generateMipmaps = true;
            texture.minFilter = THREE.LinearMipmapLinearFilter;
            texture.colorSpace = THREE.SRGBColorSpace;

            return texture;
        }

        initEvents() {
            const el = this.renderer.domElement;

            // Pointer Events (Mouse + Touch Unified)
            el.addEventListener('pointerdown', this.onPointerDown.bind(this), { passive: false });
            window.addEventListener('pointermove', this.onPointerMove.bind(this), { passive: true });
            window.addEventListener('pointerup', this.onPointerUp.bind(this), { passive: true });
            window.addEventListener('pointercancel', this.onPointerUp.bind(this), { passive: true });

            // Raycasting Click (Tap)
            el.addEventListener('click', this.onClick.bind(this));

            // Window Resize
            window.addEventListener('resize', this.onWindowResize.bind(this));
        }

        onPointerDown(e) {
            this.isDragging = true;
            this.hasInteracted = true;
            this.isAutoRotating = false;

            this.pointerDownPos.x = e.clientX;
            this.pointerDownPos.y = e.clientY;
            this.previousPointerPos.x = e.clientX;
            this.previousPointerPos.y = e.clientY;

            // Reset velocity on drag start
            this.angularVelocity.x = 0;
            this.angularVelocity.y = 0;

            if (this.idleTimer) clearTimeout(this.idleTimer);
        }

        onPointerMove(e) {
            // Update Raycaster Coordinates relative to canvas
            const rect = this.renderer.domElement.getBoundingClientRect();
            this.mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            this.mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

            if (this.isDragging) {
                const deltaX = e.clientX - this.previousPointerPos.x;
                const deltaY = e.clientY - this.previousPointerPos.y;

                // Scale rotation delta smoothly
                this.cubeGroup.rotation.y += deltaX * 0.0075;
                this.cubeGroup.rotation.x += deltaY * 0.0075;

                // Track momentum velocity
                this.angularVelocity.y = deltaX * 0.0065;
                this.angularVelocity.x = deltaY * 0.0065;

                this.previousPointerPos.x = e.clientX;
                this.previousPointerPos.y = e.clientY;

                // Hide tooltip while actively rotating
                this.hideTooltip();
            } else {
                // Check Raycast Hover when idle or moving without drag
                this.checkHover(e.clientX, e.clientY);
            }
        }

        onPointerUp(e) {
            if (!this.isDragging) return;
            this.isDragging = false;

            // Restart idle auto-rotation after 3.5 seconds of inactivity
            if (this.idleTimer) clearTimeout(this.idleTimer);
            this.idleTimer = setTimeout(() => {
                this.isAutoRotating = true;
            }, 3500);
        }

        onClick(e) {
            // Ensure this was a click, not a drag release
            const dist = Math.hypot(e.clientX - this.pointerDownPos.x, e.clientY - this.pointerDownPos.y);
            if (dist > 7) return; // Dragged, not a click

            if (this.hoveredTile && this.hoveredTile.userData.college) {
                this.openCollegeDrawer(this.hoveredTile.userData.college);
            }
        }

        checkHover(clientX, clientY) {
            if (!this.camera || !this.cubeGroup) return;

            this.raycaster.setFromCamera(this.mouse, this.camera);
            const intersects = this.raycaster.intersectObjects(this.tiles);

            if (intersects.length > 0) {
                const hitTile = intersects[0].object;

                if (this.hoveredTile !== hitTile) {
                    // Reset previous tile
                    this.resetHoveredTile();

                    // Apply subtle golden emissive highlight
                    this.hoveredTile = hitTile;
                    this.hoveredTile.material.emissive.setHex(0xF4CB45);
                    this.hoveredTile.material.emissiveIntensity = 0.35;
                    this.container.style.cursor = 'pointer';
                }

                // Show tooltip
                this.showTooltip(clientX, clientY, hitTile.userData.college);
            } else {
                this.resetHoveredTile();
                this.hideTooltip();
                this.container.style.cursor = this.isDragging ? 'grabbing' : 'grab';
            }
        }

        resetHoveredTile() {
            if (this.hoveredTile) {
                this.hoveredTile.material.emissive.setHex(0x000000);
                this.hoveredTile.material.emissiveIntensity = 0;
                this.hoveredTile = null;
            }
        }

        showTooltip(clientX, clientY, college) {
            if (!this.tooltip || !college) return;

            const rect = this.wrapper.getBoundingClientRect();
            const relX = clientX - rect.left;
            const relY = clientY - rect.top;

            this.tooltip.innerHTML = `
                <div class="tooltip-top-row">
                    <span class="tooltip-abbr-badge">${college.shortName}</span>
                    <span class="tooltip-fullname">${college.fullName}</span>
                </div>
                <div class="tooltip-sub">
                    <span>${college.campusLocation || 'Kolkata, WB'}</span>
                    <span>&bull;</span>
                    <span class="tooltip-hint">Click to inspect &rarr;</span>
                </div>
            `;

            this.tooltip.style.left = `${relX}px`;
            this.tooltip.style.top = `${relY}px`;
            this.tooltip.classList.add('is-visible');
        }

        hideTooltip() {
            if (this.tooltip) {
                this.tooltip.classList.remove('is-visible');
            }
        }

        openCollegeDrawer(college) {
            const drawer = document.getElementById('collegeInfoDrawer');
            const backdrop = document.getElementById('collegeDrawerBackdrop');
            if (!drawer || !backdrop) return;

            // Populate Drawer Fields
            document.getElementById('drawerAbbr').innerText = college.shortName;
            document.getElementById('drawerFullName').innerText = college.fullName;
            document.getElementById('drawerCategory').innerText = college.category || 'Collegiate Institution';
            document.getElementById('drawerLocation').innerText = college.campusLocation || 'Kolkata, West Bengal';
            document.getElementById('drawerEst').innerText = college.established ? `Est. ${college.established}` : 'Kolkata Campus';
            document.getElementById('drawerDesc').innerText = college.description || 'Verified collegiate institution in the Kolkata higher education directory.';

            const websiteBtn = document.getElementById('drawerWebsiteBtn');
            if (websiteBtn) {
                if (college.officialWebsite) {
                    websiteBtn.href = college.officialWebsite;
                    websiteBtn.style.display = 'inline-flex';
                } else {
                    websiteBtn.style.display = 'none';
                }
            }

            // Open Modal
            drawer.classList.add('is-open');
            backdrop.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }

        closeCollegeDrawer() {
            const drawer = document.getElementById('collegeInfoDrawer');
            const backdrop = document.getElementById('collegeDrawerBackdrop');
            if (drawer) drawer.classList.remove('is-open');
            if (backdrop) backdrop.classList.remove('is-open');
            document.body.style.overflow = '';
        }

        initUIControls() {
            // Shuffle Button
            const shuffleBtn = document.getElementById('btnShuffleCube');
            if (shuffleBtn) {
                shuffleBtn.addEventListener('click', () => this.shuffleCube());
            }

            // Reset View Button
            const resetBtn = document.getElementById('btnResetCube');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => this.resetView());
            }

            // Auto-Rotate Toggle Button
            const autoBtn = document.getElementById('btnToggleAutoRotate');
            if (autoBtn) {
                autoBtn.addEventListener('click', () => {
                    this.isAutoRotating = !this.isAutoRotating;
                    autoBtn.querySelector('.cube-btn-label').innerText = this.isAutoRotating ? 'Pause Drift' : 'Auto Drift';
                });
            }

            // Explore Directory Modal Triggers
            const openDirBtn = document.getElementById('btnOpenCollegeDirectory');
            const dirBackdrop = document.getElementById('directoryModalBackdrop');
            const dirModal = document.getElementById('directoryModalContent');
            const closeDirBtn = document.getElementById('directoryModalClose');

            if (openDirBtn && dirModal && dirBackdrop) {
                openDirBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.openDirectoryModal();
                });
            }

            if (closeDirBtn) {
                closeDirBtn.addEventListener('click', () => this.closeDirectoryModal());
            }

            if (dirBackdrop) {
                dirBackdrop.addEventListener('click', () => this.closeDirectoryModal());
            }

            // College Drawer Close Buttons
            const drawerCloseBtn = document.getElementById('drawerCloseBtn');
            const drawerBackdrop = document.getElementById('collegeDrawerBackdrop');
            if (drawerCloseBtn) drawerCloseBtn.addEventListener('click', () => this.closeCollegeDrawer());
            if (drawerBackdrop) drawerBackdrop.addEventListener('click', () => this.closeCollegeDrawer());

            // Global Escape Key to close drawers
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeCollegeDrawer();
                    this.closeDirectoryModal();
                }
            });

            // Directory Live Search Input Filter
            const searchInput = document.getElementById('directorySearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    this.filterDirectoryCards(e.target.value.toLowerCase());
                });
            }
        }

        openDirectoryModal() {
            const modal = document.getElementById('directoryModalContent');
            const backdrop = document.getElementById('directoryModalBackdrop');
            if (!modal || !backdrop) return;

            this.renderDirectoryCards();
            modal.classList.add('is-open');
            backdrop.classList.add('is-open');
            document.body.style.overflow = 'hidden';

            const searchInput = document.getElementById('directorySearchInput');
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }
        }

        closeDirectoryModal() {
            const modal = document.getElementById('directoryModalContent');
            const backdrop = document.getElementById('directoryModalBackdrop');
            if (modal) modal.classList.remove('is-open');
            if (backdrop) backdrop.classList.remove('is-open');
            document.body.style.overflow = '';
        }

        renderDirectoryCards() {
            const container = document.getElementById('directoryCardsContainer');
            if (!container || !window.KolkataCollegeDirectory) return;

            const list = window.KolkataCollegeDirectory.getAll();
            container.innerHTML = list.map(item => `
                <div class="directory-card-item" data-id="${item.id}" data-name="${item.fullName.toLowerCase()} ${item.shortName.toLowerCase()}">
                    <div class="card-item-top">
                        <span class="card-item-abbr">${item.shortName}</span>
                        <span class="card-item-cat">${item.category || 'College'}</span>
                    </div>
                    <div class="card-item-name">${item.fullName}</div>
                    <div class="card-item-loc">
                        <span>📍</span>
                        <span>${item.campusLocation || 'Kolkata, West Bengal'}</span>
                    </div>
                </div>
            `).join('');

            // Click card to open drawer
            container.querySelectorAll('.directory-card-item').forEach(card => {
                card.addEventListener('click', () => {
                    const id = card.getAttribute('data-id');
                    const college = window.KolkataCollegeDirectory.getById(id);
                    if (college) {
                        this.closeDirectoryModal();
                        setTimeout(() => this.openCollegeDrawer(college), 150);
                    }
                });
            });
        }

        filterDirectoryCards(query) {
            const items = document.querySelectorAll('.directory-card-item');
            items.forEach(item => {
                const text = item.getAttribute('data-name') || '';
                item.style.display = text.includes(query) ? 'flex' : 'none';
            });
        }

        shuffleCube() {
            this.isAutoRotating = false;
            this.isAnimatingView = true;
            this.viewAnimationProgress = 0;

            this.viewStartRot = {
                x: this.cubeGroup.rotation.x,
                y: this.cubeGroup.rotation.y,
                z: this.cubeGroup.rotation.z
            };

            // Multi-axis tumble targets (adds full rotations + offset)
            const randomX = (Math.PI * 2 * (Math.random() > 0.5 ? 1 : -1)) + (Math.random() * 0.8 - 0.4);
            const randomY = (Math.PI * 4 * (Math.random() > 0.5 ? 1 : -1)) + (Math.random() * 0.8 - 0.4);
            const randomZ = (Math.PI * (Math.random() > 0.5 ? 1 : -1)) * 0.5;

            this.viewTargetRot = {
                x: this.viewStartRot.x + randomX,
                y: this.viewStartRot.y + randomY,
                z: this.viewStartRot.z + randomZ
            };
        }

        resetView() {
            this.isAutoRotating = false;
            this.isAnimatingView = true;
            this.viewAnimationProgress = 0;

            this.viewStartRot = {
                x: this.cubeGroup.rotation.x,
                y: this.cubeGroup.rotation.y,
                z: this.cubeGroup.rotation.z
            };

            this.viewTargetRot = {
                x: -0.32,
                y: 0.62,
                z: 0
            };
        }

        playEntranceAnimation() {
            this.cubeGroup.scale.set(0.2, 0.2, 0.2);
            let progress = 0;

            const entranceInterval = setInterval(() => {
                progress += 0.04;
                const ease = this.easeOutBack(Math.min(progress, 1));
                this.cubeGroup.scale.set(ease, ease, ease);

                if (progress >= 1) {
                    this.cubeGroup.scale.set(1, 1, 1);
                    clearInterval(entranceInterval);
                }
            }, 16);
        }

        easeOutBack(x) {
            const c1 = 1.70158;
            const c3 = c1 + 1;
            return 1 + c3 * Math.pow(x - 1, 3) + c1 * Math.pow(x - 1, 2);
        }

        easeInOutCubic(x) {
            return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;
        }

        initObserver() {
            // IntersectionObserver: Pause rendering when scrolled out of viewport
            if ('IntersectionObserver' in window && this.wrapper) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        this.isRunning = entry.isIntersecting;
                    });
                }, { threshold: 0.05 });
                observer.observe(this.wrapper);
            }

            // Tab Visibility: Pause animation when tab is inactive
            document.addEventListener('visibilitychange', () => {
                this.isRunning = !document.hidden;
            });
        }

        onWindowResize() {
            if (!this.container || !this.renderer || !this.camera) return;

            const width = this.container.clientWidth;
            const height = this.container.clientHeight;

            this.camera.aspect = width / height;

            // Responsive camera distance adjustments
            if (width < 500) {
                this.camera.position.z = 11.5; // Back out camera on small mobile screens
            } else if (width < 768) {
                this.camera.position.z = 10.2;
            } else {
                this.camera.position.z = 9.2;
            }

            this.camera.updateProjectionMatrix();
            this.renderer.setSize(width, height);
        }

        animate() {
            requestAnimationFrame(this.animate.bind(this));

            if (!this.isRunning) return;

            const time = performance.now() * 0.001;

            // 1. Handle Smooth View Transition (Shuffle / Reset)
            if (this.isAnimatingView) {
                this.viewAnimationProgress += 0.022;
                const t = this.easeInOutCubic(Math.min(this.viewAnimationProgress, 1));

                this.cubeGroup.rotation.x = this.viewStartRot.x + (this.viewTargetRot.x - this.viewStartRot.x) * t;
                this.cubeGroup.rotation.y = this.viewStartRot.y + (this.viewTargetRot.y - this.viewStartRot.y) * t;
                this.cubeGroup.rotation.z = this.viewStartRot.z + (this.viewTargetRot.z - this.viewStartRot.z) * t;

                if (this.viewAnimationProgress >= 1) {
                    this.isAnimatingView = false;
                    // Resume subtle auto-rotation
                    this.idleTimer = setTimeout(() => {
                        this.isAutoRotating = true;
                    }, 2000);
                }
            } else {
                // 2. Drag Momentum & Velocity Damping
                if (!this.isDragging) {
                    this.cubeGroup.rotation.y += this.angularVelocity.y;
                    this.cubeGroup.rotation.x += this.angularVelocity.x;

                    this.angularVelocity.x *= 0.92;
                    this.angularVelocity.y *= 0.92;

                    // 3. Gentle Continuous Idle Drift
                    if (this.isAutoRotating && Math.abs(this.angularVelocity.x) < 0.0001 && Math.abs(this.angularVelocity.y) < 0.0001) {
                        this.cubeGroup.rotation.y += 0.0028;
                        this.cubeGroup.rotation.x += Math.sin(time * 0.7) * 0.0008;
                    }
                }
            }

            // Render Frame
            this.renderer.render(this.scene, this.camera);
        }
    }

    // Auto-initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('kolkataCubeContainer')) {
            window.ApexKolkataCube = new KolkataRubikCube({
                containerId: 'kolkataCubeContainer',
                wrapperId: 'cubeStageWrapper',
                tooltipId: 'cubeTileTooltip',
                fallbackId: 'cubeFallbackView'
            });
        }
    });

})();

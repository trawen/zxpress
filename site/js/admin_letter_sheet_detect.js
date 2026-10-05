/**
 * Detect letter sheet bounding boxes on a dark-background scan.
 * Expects high-contrast photos: black desk, light paper (1–N sheets).
 *
 * @param {HTMLImageElement|HTMLCanvasElement|ImageBitmap} source
 * @param {{maxDetectWidth?:number,threshold?:number,minAreaFrac?:number,padFrac?:number}} [opts]
 * @returns {Promise<Array<{x:number,y:number,width:number,height:number}>>}
 *   Boxes in natural/source pixel coordinates.
 */
(function (global) {
	'use strict';

	function luminance(r, g, b) {
		return 0.2126 * r + 0.7152 * g + 0.0722 * b;
	}

	function morph3x3(src, w, h, mode) {
		var dst = new Uint8Array(src.length);
		var y, x, dy, dx, xx, yy, on, idx;
		for (y = 0; y < h; y++) {
			for (x = 0; x < w; x++) {
				on = mode === 'erode' ? 1 : 0;
				for (dy = -1; dy <= 1; dy++) {
					for (dx = -1; dx <= 1; dx++) {
						xx = x + dx;
						yy = y + dy;
						if (xx < 0 || yy < 0 || xx >= w || yy >= h) {
							if (mode === 'erode') {
								on = 0;
							}
							continue;
						}
						if (src[yy * w + xx]) {
							if (mode === 'dilate') {
								on = 1;
							}
						} else if (mode === 'erode') {
							on = 0;
						}
					}
				}
				dst[y * w + x] = on ? 1 : 0;
			}
		}
		return dst;
	}

	function detectFromImageData(imageData, naturalW, naturalH, opts) {
		var thr = opts.threshold != null ? opts.threshold : 48;
		var minAreaFrac = opts.minAreaFrac != null ? opts.minAreaFrac : 0.012;
		var padFrac = opts.padFrac != null ? opts.padFrac : 0.015;
		var w = imageData.width;
		var h = imageData.height;
		var data = imageData.data;
		var mask = new Uint8Array(w * h);
		var i, p, y;
		for (i = 0, p = 0; i < w * h; i++, p += 4) {
			mask[i] = luminance(data[p], data[p + 1], data[p + 2]) > thr ? 1 : 0;
		}

		var m = morph3x3(morph3x3(mask, w, h, 'dilate'), w, h, 'dilate');
		m = morph3x3(morph3x3(m, w, h, 'erode'), w, h, 'erode');
		m = morph3x3(m, w, h, 'dilate');

		var labels = new Int32Array(w * h);
		var nlab = 0;
		var comps = [];
		var stack = new Int32Array(w * h);
		var sp, cur, cx, cy, nx, ny, n, minx, maxx, miny, maxy, area;
		var neigh;

		for (i = 0; i < w * h; i++) {
			if (!m[i] || labels[i]) {
				continue;
			}
			nlab++;
			sp = 0;
			stack[sp++] = i;
			labels[i] = nlab;
			minx = i % w;
			maxx = minx;
			miny = (i / w) | 0;
			maxy = miny;
			area = 0;
			while (sp) {
				cur = stack[--sp];
				area++;
				cx = cur % w;
				cy = (cur / w) | 0;
				if (cx < minx) minx = cx;
				if (cx > maxx) maxx = cx;
				if (cy < miny) miny = cy;
				if (cy > maxy) maxy = cy;
				neigh = [cur - 1, cur + 1, cur - w, cur + w];
				for (n = 0; n < 4; n++) {
					var ni = neigh[n];
					if (ni < 0 || ni >= w * h || !m[ni] || labels[ni]) {
						continue;
					}
					nx = ni % w;
					if (Math.abs(nx - cx) > 1) {
						continue;
					}
					labels[ni] = nlab;
					stack[sp++] = ni;
				}
			}
			comps.push({ minx: minx, miny: miny, maxx: maxx, maxy: maxy, area: area });
		}

		var imgArea = w * h;
		var boxes = [];
		for (i = 0; i < comps.length; i++) {
			var c = comps[i];
			var bw = c.maxx - c.minx + 1;
			var bh = c.maxy - c.miny + 1;
			if (c.area < imgArea * minAreaFrac) continue;
			if (bw < w * 0.04 || bh < h * 0.04) continue;
			if (c.area / (bw * bh) < 0.22) continue;
			var ar = bw / bh;
			if (ar > 4 || ar < 0.12) continue;
			boxes.push({
				x: c.minx,
				y: c.miny,
				width: bw,
				height: bh,
				area: c.area
			});
		}

		boxes.sort(function (a, b) { return b.area - a.area; });
		var kept = [];
		for (i = 0; i < boxes.length; i++) {
			var b = boxes[i];
			var inside = false;
			for (var k = 0; k < kept.length; k++) {
				var kb = kept[k];
				var ix0 = Math.max(b.x, kb.x);
				var iy0 = Math.max(b.y, kb.y);
				var ix1 = Math.min(b.x + b.width, kb.x + kb.width);
				var iy1 = Math.min(b.y + b.height, kb.y + kb.height);
				if (ix1 > ix0 && iy1 > iy0) {
					var inter = (ix1 - ix0) * (iy1 - iy0);
					if (inter > 0.5 * b.area) {
						inside = true;
						break;
					}
				}
			}
			if (!inside) {
				kept.push(b);
			}
		}

		var rowH = h * 0.2;
		kept.sort(function (a, b) {
			var ra = Math.floor(a.y / rowH);
			var rb = Math.floor(b.y / rowH);
			if (ra !== rb) return ra - rb;
			return a.x - b.x;
		});

		var scaleX = naturalW / w;
		var scaleY = naturalH / h;
		return kept.map(function (b) {
			var px = Math.round(b.width * padFrac);
			var py = Math.round(b.height * padFrac);
			var x = Math.max(0, Math.round((b.x - px) * scaleX));
			var y = Math.max(0, Math.round((b.y - py) * scaleY));
			var width = Math.min(naturalW - x, Math.round((b.width + 2 * px) * scaleX));
			var height = Math.min(naturalH - y, Math.round((b.height + 2 * py) * scaleY));
			return { x: x, y: y, width: width, height: height };
		});
	}

	function drawSourceToCanvas(source, maxDetectWidth) {
		var nw = source.naturalWidth || source.width;
		var nh = source.naturalHeight || source.height;
		if (!nw || !nh) {
			throw new Error('image has no dimensions');
		}
		var scale = Math.min(1, maxDetectWidth / nw);
		var w = Math.max(1, Math.round(nw * scale));
		var h = Math.max(1, Math.round(nh * scale));
		var canvas = document.createElement('canvas');
		canvas.width = w;
		canvas.height = h;
		var ctx = canvas.getContext('2d', { willReadFrequently: true });
		if (!ctx) {
			throw new Error('2d context unavailable');
		}
		ctx.drawImage(source, 0, 0, w, h);
		return {
			imageData: ctx.getImageData(0, 0, w, h),
			naturalW: nw,
			naturalH: nh
		};
	}

	function detectLetterSheetsFromImage(source, opts) {
		opts = opts || {};
		var maxDetectWidth = opts.maxDetectWidth || 900;
		return Promise.resolve().then(function () {
			var drawn = drawSourceToCanvas(source, maxDetectWidth);
			return detectFromImageData(drawn.imageData, drawn.naturalW, drawn.naturalH, opts);
		});
	}

	/**
	 * Crop a natural-pixel region from an HTMLImageElement to a JPEG Blob.
	 */
	function cropImageRegionToBlob(img, region, maxSide, quality) {
		maxSide = maxSide || 3500;
		quality = quality == null ? 0.85 : quality;
		return new Promise(function (resolve, reject) {
			var sx = Math.max(0, Math.round(region.x));
			var sy = Math.max(0, Math.round(region.y));
			var sw = Math.max(1, Math.round(region.width));
			var sh = Math.max(1, Math.round(region.height));
			var nw = img.naturalWidth || img.width;
			var nh = img.naturalHeight || img.height;
			if (sx + sw > nw) sw = nw - sx;
			if (sy + sh > nh) sh = nh - sy;
			if (sw < 1 || sh < 1) {
				reject(new Error('empty crop region'));
				return;
			}
			var scale = 1;
			var longSide = Math.max(sw, sh);
			if (longSide > maxSide) {
				scale = maxSide / longSide;
			}
			var dw = Math.max(1, Math.round(sw * scale));
			var dh = Math.max(1, Math.round(sh * scale));
			var canvas = document.createElement('canvas');
			canvas.width = dw;
			canvas.height = dh;
			var ctx = canvas.getContext('2d');
			if (!ctx) {
				reject(new Error('2d context unavailable'));
				return;
			}
			ctx.imageSmoothingEnabled = true;
			ctx.imageSmoothingQuality = 'high';
			ctx.drawImage(img, sx, sy, sw, sh, 0, 0, dw, dh);
			canvas.toBlob(function (blob) {
				if (!blob) {
					reject(new Error('toBlob failed'));
					return;
				}
				resolve(blob);
			}, 'image/jpeg', quality);
		});
	}

	global.detectLetterSheetsFromImage = detectLetterSheetsFromImage;
	global.cropImageRegionToBlob = cropImageRegionToBlob;
})(typeof window !== 'undefined' ? window : globalThis);

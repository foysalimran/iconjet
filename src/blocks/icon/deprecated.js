/**
 * Block deprecations.
 *
 * Strategy for future changes:
 *
 * 1. The saved markup of every published version must stay reproducible.
 *    Any change to save() output (attributes on the wrapper, the SVG, the
 *    link, inline styles) needs a deprecation entry, or existing posts show
 *    "This block contains unexpected or invalid content".
 * 2. Before changing save.js, copy the current save() and its attributes
 *    into an entry here: { attributes, supports, save, migrate? }. Newest
 *    first. Use `migrate` to map renamed attributes.
 * 3. The SVG itself is stored in the `svg` attribute, so updating an icon
 *    pack never invalidates blocks: old posts keep the SVG they were saved
 *    with. Do not derive save() output from pack data.
 * 4. Changes to shared/svg.js affect this block and Social Icons: add
 *    deprecations to both.
 * 5. Test with real posts saved by the previous release (editor loads them
 *    without validation errors, then re-saving upgrades them).
 *
 * Version 1.0.0 is the first release, so there are no deprecations yet.
 */
const deprecated = [];

export default deprecated;

<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The site's colours, and every shade the stylesheets derive from them.
 *
 * The four identity colours (§23) used to be constants in `tokens.css`, which
 * meant a rebrand was a developer's job — the dependence §19 says the handover
 * must remove. They are now four settings rows, and this class turns them into
 * the custom properties the whole theme reads, public site and panel alike.
 *
 * WHY THE SHADES ARE DERIVED RATHER THAN ALSO STORED
 *
 * The palette is not four colours; it is four colours and ten shades of them —
 * three navies for the surfaces that must read as the frame around the page, a
 * near-white navy tint, a darkened lavender for link text, a darkened orange
 * that white can sit on, and four warm neutrals off the gold. Asking a client
 * to choose fourteen colours is asking them to get eleven of them wrong. They
 * choose four; this derives the rest, in OKLab so that one step means the same
 * step at every hue.
 *
 * WHY THAT IS SAFE
 *
 * §10.2 fixed the palette because every contrast guarantee in §10.8 was
 * calculated against those exact values — `--action-600` exists precisely
 * because white on the identity's burnt orange fails AA below 18px/700. That
 * objection is answered here rather than dismissed: the two shades that carry
 * a contrast guarantee are not derived by a fixed step at all, but by stepping
 * until the guarantee holds (`Color::legibleOn`). Whatever four colours a
 * client picks, link text still clears 4.5:1 on paper and white still clears
 * 4.5:1 on the action colour. The panel measures and reports the rest, so a
 * choice that weakens something is visible at the moment it is made rather
 * than discovered by a visitor who cannot read the page.
 *
 * THE APPROVED IDENTITY IS NEVER RE-DERIVED
 *
 * A family whose base colour still holds its approved value serves the shades
 * §23 approved, byte for byte. The derivation reproduces them to within four
 * parts in 255 — invisible, but "invisible" is not the same as "unchanged",
 * and a reviewer comparing this build against the identity document is owed
 * the latter.
 */
class Palette
{
    /** The settings group the four rows live in. */
    public const GROUP = 'brand';

    /** The four approved colours (§23). */
    public const IDENTITY = [
        'navy' => '#002546',
        'lavender' => '#8685D8',
        'orange' => '#D7653B',
        'gold' => '#DCAD75',
    ];

    /**
     * A shade one step from its base — it must move when the base moves.
     *
     * `--navy-800` is the hover state of a `--navy-900` surface: what matters
     * is that it sits a little above it, not where on the scale it sits.
     */
    private const NEAR = 'near';

    /**
     * A shade at a fixed lightness, taking only hue and chroma from its base.
     *
     * `--paper-warm` is the page ground, and it is a pale warm neutral because
     * that is the job, not because gold happens to be light. Derived as an
     * offset it followed the gold down: a client picking a deep amber got a
     * mid-tone page background, every body-text ratio on the site fell with
     * it, and the darker the choice the darker the whole site went. The same
     * held for `--navy-100`, a pale tint that turned white the moment somebody
     * picked a dominant colour lighter than the identity's.
     *
     * So these five are pinned. The lightness is the approved shade's own, the
     * hue and the chroma are the client's, and a pale tint stays a pale tint at
     * any base colour.
     */
    private const PINNED = 'pinned';

    /**
     * Every shade: `[family, kind, lightness, chroma factor, the §23 value]`.
     *
     * The numbers were measured off the approved shades themselves, so the
     * ramp a client gets has the proportions the identity was drawn with.
     * `lightness` is an offset from the base for NEAR and an absolute
     * lightness for PINNED.
     */
    private const SHADES = [
        // The frame around the page: a step below the dominant colour and two
        // steps above it. All three follow wherever it goes.
        'navy-950' => ['navy', self::NEAR, -0.0484, 0.763, '#001A31'],
        'navy-800' => ['navy', self::NEAR, 0.0592, 1.153, '#06345C'],
        'navy-700' => ['navy', self::NEAR, 0.1135, 1.278, '#0B4370'],
        // A pale tint of the dominant colour — pinned, see above.
        'navy-100' => ['navy', self::PINNED, 0.9337, 0.164, '#E3EAF1'],

        /*
         * The warm neutrals: the page ground, the card tint, the section band
         * and the placeholder. All four are the premium colour at very low
         * chroma, which is what makes the page read warm beside the dominant
         * colour rather than clinical (§10.2) — and all four are pinned,
         * because a page ground is pale by definition.
         *
         * BEFORE the contracted shades below, and the order is load-bearing:
         * `--paper-warm` is what link text has to be legible against, so it
         * has to exist by the time that is measured.
         */
        'gold-100' => ['gold', self::PINNED, 0.9469, 0.249, '#F6ECDD'],
        'sand' => ['gold', self::PINNED, 0.9510, 0.118, '#F3EEE7'],
        'paper-warm' => ['gold', self::PINNED, 0.9800, 0.081, '#FBF8F3'],
        'placeholder-warm' => ['gold', self::PINNED, 0.9130, 0.189, '#E9E1D6'],

        // The two that carry a contrast guarantee — see CONTRACTS.
        'lavender-700' => ['lavender', self::NEAR, -0.1209, 1.198, '#5F5EBE'],
        'action-600' => ['orange', self::NEAR, -0.0818, 0.892, '#B4522C'],
        // One step below it again, for the call to action's hover. It was the
        // last hand-picked shade in the stylesheets — a literal `#9C4525` that
        // would have stayed burnt orange under a green button.
        'action-700' => ['orange', self::NEAR, -0.1393, 0.809, '#9C4525'],
    ];

    /**
     * The shades that must stay legible whatever the client picks.
     *
     * `token => [what it must be legible against, the ratio]`, where the
     * reference is either a literal colour or another token — a token being
     * one this list's own entry comes after in SHADES. Both are body text at
     * ordinary sizes, so both take AA's 4.5:1, not the 3:1 large text is
     * allowed: neither of these is only ever large.
     */
    private const CONTRACTS = [
        /*
         * Link text and the focus ring, on the page ground.
         *
         * Against `--paper-warm`, not white, and the difference is not
         * pedantry: the ground is a shade darker than white, so a lavender
         * derived to clear 4.5:1 on white landed at 4.29:1 on the page it
         * actually sits on. Contracting against the darker of the two grounds
         * covers the white one too, since white can only give more.
         */
        'lavender-700' => ['--paper-warm', 4.5],
        // White sits on this: buttons, chips, the danger action.
        'action-600' => ['#FFFFFF', 4.5],
    ];

    /** @var array<string, string>|null */
    private ?array $tokens = null;

    public function __construct(private readonly Settings $settings) {}

    /**
     * The four base colours as they stand — the client's, or the identity's.
     *
     * A stored value that is not a colour is ignored rather than rendered. It
     * would otherwise reach the stylesheet as a broken declaration and take
     * the whole `:root` block down with it in some browsers, which turns one
     * bad character into a site with no colours at all.
     *
     * @return array<string, string>
     */
    public function colours(): array
    {
        $colours = [];

        foreach (self::IDENTITY as $name => $approved) {
            $stored = $this->settings->get(self::GROUP.'.colour.'.$name);

            $colours[$name] = is_string($stored) && preg_match('/^#[0-9a-fA-F]{6}$/', trim($stored))
                ? strtoupper(trim($stored))
                : $approved;
        }

        return $colours;
    }

    /** True while every colour still holds the value §23 approved. */
    public function isIdentity(): bool
    {
        return $this->colours() === self::IDENTITY;
    }

    /**
     * Every custom property the theme reads, keyed by property name.
     *
     * Three kinds: the four base colours, the four channel triplets that the
     * stylesheets' hundred-odd low-alpha washes are built from, and the ten
     * derived shades.
     *
     * @return array<string, string>
     */
    public function tokens(): array
    {
        if ($this->tokens !== null) {
            return $this->tokens;
        }

        $colours = $this->colours();
        $tokens = [];

        // Base and channels. `--navy-rgb: 0 37 70` is what lets a stylesheet
        // write `rgb(var(--navy-rgb) / 0.08)` and have the wash follow navy.
        foreach (['navy' => 'navy-900', 'lavender' => 'lavender-500', 'orange' => 'orange-500', 'gold' => 'gold-400'] as $family => $token) {
            $colour = Color::fromHex($colours[$family]);

            $tokens["--{$token}"] = $colour->toHex();
            $tokens["--{$family}-rgb"] = $colour->channels();
        }

        foreach (self::SHADES as $token => [$family, $kind, $lightness, $chroma, $approved]) {
            $base = Color::fromHex($colours[$family]);

            // An untouched family keeps the approved ramp exactly.
            $shade = match (true) {
                $colours[$family] === self::IDENTITY[$family] => Color::fromHex($approved),
                $kind === self::NEAR => $base->shade($lightness, $chroma),
                default => $base->at($lightness, $chroma),
            };

            /*
             * The guarantee is checked even on an approved shade, because a
             * shade can be made illegible by a colour from another family: the
             * approved lavender is link text on a ground tinted with the
             * client's gold. It never moves the approved palette, which passes
             * — `legibleOn` returns a colour that already clears the ratio
             * untouched.
             */
            if (isset(self::CONTRACTS[$token])) {
                [$against, $ratio] = self::CONTRACTS[$token];

                $shade = $shade->legibleOn(
                    Color::fromHex(str_starts_with($against, '--') ? $tokens[$against] : $against),
                    $ratio,
                );
            }

            $tokens["--{$token}"] = $shade->toHex();
        }

        return $this->tokens = $tokens;
    }

    /**
     * The tokens as a `:root` block, for the one <style> in app.blade.php.
     *
     * Rendered server-side into every document — public pages, the panel, the
     * sign-in screen and the error pages all share that template — so the
     * colours are correct in the very first paint and under SSR, with no
     * flash of the built-in palette and no JavaScript involved.
     *
     * Values are filtered to hex and channel triplets on the way out, so
     * nothing that reaches this string can close the tag or add a declaration.
     */
    public function css(): string
    {
        $declarations = [];

        foreach ($this->tokens() as $property => $value) {
            if (preg_match('/^(#[0-9A-F]{6}|\d{1,3} \d{1,3} \d{1,3})$/', $value) === 1) {
                $declarations[] = "{$property}:{$value}";
            }
        }

        return ':root{'.implode(';', $declarations).'}';
    }

    /** The browser chrome colour — the dominant colour, whatever it now is. */
    public function themeColor(): string
    {
        return $this->tokens()['--navy-900'];
    }

    /**
     * The tokens, or the approved identity if anything at all goes wrong.
     *
     * For the error pages. Those are deliberately the dumbest templates in the
     * application — static markup, inline styles, no build step — because a
     * 500 means something has already failed, and a page that needs the
     * database in order to say "something went wrong" is a page that will not
     * render exactly when it is needed. Reading a settings row would have put
     * that dependency back.
     *
     * So they ask for the colours and accept not getting them. A client who
     * has rebranded sees their own colours on a 404; a site whose database is
     * unreachable still gets an error page, in the identity's.
     *
     * @return array<string, string>
     */
    public static function safely(): array
    {
        try {
            return app(self::class)->tokens();
        } catch (\Throwable) {
            return (new self(new Settings))->identityTokens();
        }
    }

    /**
     * The palette exactly as §23 approved it, with nothing read from storage.
     *
     * @return array<string, string>
     */
    public function identityTokens(): array
    {
        $tokens = [];

        foreach (['navy' => 'navy-900', 'lavender' => 'lavender-500', 'orange' => 'orange-500', 'gold' => 'gold-400'] as $family => $token) {
            $colour = Color::fromHex(self::IDENTITY[$family]);
            $tokens["--{$token}"] = $colour->toHex();
            $tokens["--{$family}-rgb"] = $colour->channels();
        }

        foreach (self::SHADES as $token => [, , , $approved]) {
            $tokens["--{$token}"] = $approved;
        }

        return $tokens;
    }

    /**
     * What the current palette does to the pairs the design depends on.
     *
     * The panel draws this beside the pickers. Two of these are guaranteed by
     * derivation and cannot fail; the rest are the client's to weigh, and a
     * number they can see is the difference between an informed identity
     * decision and an accident.
     *
     * @return list<array{pair: string, ratio: float, minimum: float, passes: bool}>
     */
    public function report(): array
    {
        $t = $this->tokens();
        $white = Color::fromHex('#FFFFFF');
        $navy = Color::fromHex($t['--navy-900']);
        $ground = Color::fromHex($t['--paper-warm']);

        $pairs = [
            // Every navy surface on the site carries white text.
            'white_on_navy' => [$white->contrast($navy), 4.5],
            // Link text and the focus ring, on the page ground.
            'link_on_paper' => [Color::fromHex($t['--lavender-700'])->contrast($ground), 4.5],
            // White on the action colour — buttons, chips, the danger action.
            'white_on_action' => [$white->contrast(Color::fromHex($t['--action-600'])), 4.5],
            // The large call to action only ever appears at 18px/700, which is
            // where AA asks for 3:1 rather than 4.5:1 (§10.2).
            'white_on_cta' => [$white->contrast(Color::fromHex($t['--orange-500'])), 3.0],
            // Gold is a text colour on navy and a rule colour everywhere else.
            'gold_on_navy' => [Color::fromHex($t['--gold-400'])->contrast($navy), 4.5],
        ];

        return array_values(array_map(
            static fn (string $pair): array => [
                'pair' => $pair,
                'ratio' => round($pairs[$pair][0], 2),
                'minimum' => $pairs[$pair][1],
                'passes' => $pairs[$pair][0] >= $pairs[$pair][1],
            ],
            array_keys($pairs),
        ));
    }
}

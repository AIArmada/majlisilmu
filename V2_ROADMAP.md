# Majlisilmu V2 Roadmap

**Last Updated:** 2026-03-15  
**Document Purpose:** Define the next product phase after the current v1 baseline.

The current application already behaves more like a launch-ready v1 than a strict MVP. It has public discovery, submission, moderation, authenticated user flows, institution self-service, API endpoints, and notification infrastructure.

V2 should not be "more of everything." It should deepen the product where leverage is highest.

---

## V2 Thesis

Turn Majlisilmu from a discovery application into an organizer operating system.

The main v2 question is not "What else can users browse?" It is:

- how can organizers publish recurring programs with less effort
- how can operators manage attendance and members with less manual work
- how can moderators review faster without lowering trust
- how can the platform create stronger repeat usage from both organizers and attendees

---

## Product Principles

1. Depth over breadth  
Ship fewer surfaces, but make the organizer and reviewer workflows materially better.

2. Reduce operational toil  
If a team runs weekly or monthly programs, the platform should remove repeat admin work.

3. Improve supply quality before automating approvals  
Reviewer tooling should mature before trust-score or auto-approval logic becomes a major focus.

4. Retention should follow real utility  
Digests, follow systems, and push become more valuable once the organizer workflow is stronger.

---

## Strategic Tracks

### 1. Organizer Ops

This should be the center of v2.

- recurring series automation from templates into managed child events
- parent/child program management with per-session exceptions
- institution self-service attendee export
- invitation delivery flow instead of copy/share-only links
- cleaner member and event management surfaces inside Ahli/dashboard flows

Why this matters:

- the current app can already collect and publish events
- the next gain is reducing organizer friction and increasing repeat submissions
- better organizer tooling improves event supply quality across the whole product

### 2. Attendance Operations

Once organizers can publish efficiently, the next step is operating events cleanly.

- QR-based check-in
- check-in source tracking
- post-event attendance records and attendance visibility for institutions
- basic attendance analytics for organizers

Why this matters:

- registrations are useful, but actual attendance closes the loop
- attendance operations make the product valuable on event day, not just before publishing

### 3. Moderator Leverage

Moderation already works. V2 should make it faster and more consistent.

- side-by-side submission diff view
- explicit SLA visibility in the moderation queue
- duplicate detection hints
- prioritization signals for reviewer triage

Why this matters:

- current moderation correctness is in place
- the bigger v2 win is reviewer speed, confidence, and consistency

### 4. Retention and Distribution

Retention should become smarter after organizer and moderation workflows improve.

- stronger follow and saved-search re-engagement loops
- improved digest relevance
- better push behavior for attendees and organizers
- post-event re-engagement flows

Why this matters:

- stronger event supply and cleaner operations give retention systems better input
- this creates repeat usage without needing to invent a new product surface

---

## Roadmap Priority

### Now

These are the highest-leverage v2 moves.

- recurring series automation and managed child-event generation
- institution attendee export in self-service flows
- invitation delivery workflow
- moderation diff view, SLA indicators, and duplicate hints

### Next

These should follow once organizer and reviewer flows are stronger.

- QR-based check-in
- attendance source tracking
- organizer attendance views and post-event operational reporting
- smarter follow, digest, and push behavior

### Later

These are valuable, but should not lead v2.

- trust score models and selective auto-approval
- deeper operations tooling such as Horizon-first admin refinement
- broader public personalization work
- additional public-page polish that does not improve organizer leverage

---

## What V2 Is Not

- not a second broad MVP with many unrelated features
- not more standalone admin resources for their own sake
- not primarily a public UI polish cycle
- not trust automation before reviewer tooling is mature

---

## Success Metrics

V2 is working if these improve:

- repeat submissions per institution
- percentage of recurring programs created through the series workflow
- median moderation handling time
- moderator queue backlog age
- attendee export usage by institutions
- attendee return rate for future events

---

## Suggested Execution Order

1. Complete organizer leverage first  
Recurring series automation, attendee export, and invitation delivery.

2. Upgrade moderation productivity  
Diffing, SLA visibility, and duplicate hints.

3. Add event-day operations  
QR check-in and attendance tracking.

4. Strengthen retention loops  
Smarter digests, follows, and push.

5. Introduce selective trust automation  
Only after the reviewer workflow and data quality are strong.

---

## Practical V2 Positioning

If v1 proves that people want to discover and submit Majlis events, v2 should prove that institutions want to run their recurring programs through Majlisilmu because it saves them time.

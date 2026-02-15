# GLM Homepage Alternative Design Plan

## Overview

This document outlines the plan for creating an alternative homepage design at the `/glm` route. The goal is to provide a different visual approach while maintaining the same core functionality, allowing for comparison between the two designs.

## Current Homepage Analysis

### Structure
The existing homepage at `/` uses:
- **Dark Hero Section** - 85vh height with aurora gradients, Islamic patterns, and animated effects
- **Search Interface** - Prominent search with geolocation support
- **Quick Links** - Tonight, Friday, This Week, Weekend, hashtags
- **Live Stats** - Events, Speakers, Institutions count
- **Upcoming Prayer Events** - Contextual events based on prayer times
- **Featured Events** - Horizontal carousel with 8 events
- **My Majlis** - Personal dashboard for authenticated users
- **Date Filter** - Browse by date section
- **Browse by Location** - States and topics grid
- **Upcoming Events Grid** - 3-column grid with 9 events
- **CTA Section** - Submit event call-to-action

### Design Characteristics
- Dark theme with emerald/gold accents
- Heavy use of gradients and blur effects
- Islamic pattern overlays
- Large typography
- Multiple lazy-loaded Livewire components

---

## GLM Alternative Design Concept

### Design Philosophy: Light, Modern, Bento

The GLM homepage takes a fundamentally different approach:

1. **Light Theme** - Clean white/cream background with subtle warmth
2. **Bento Grid Layout** - Modern card-based organization
3. **Visual-First** - Emphasis on event imagery
4. **Simplified Hierarchy** - Clearer content prioritization
5. **Micro-interactions** - Subtle hover effects and animations

### Color Palette

```
Primary: Emerald (maintained for brand consistency)
Secondary: Warm Sand/Gold tones
Background: Warm white (#FAFAF8)
Cards: Pure white with subtle shadows
Text: Slate-900 for headings, Slate-600 for body
Accents: Teal for interactive elements
```

### Typography

```
Headings: Outfit (existing font, but lighter weights)
Body: Outfit with increased line-height
Hero: Smaller, more refined statement
```

---

## Component Structure

### 1. Hero Section - Redesigned

**Changes from original:**
- Light background with subtle gradient
- Smaller height (60vh instead of 85vh)
- Centered content with cleaner layout
- Search bar with rounded pill design
- Floating quick filter chips below search

```
┌─────────────────────────────────────────────────────────────┐
│                                                             │
│              Find Islamic Knowledge Events                  │
│           Discover majlis ilmu near you or online          │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 🔍  Search topics, speakers, locations...      →    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  [Tonight] [Friday] [This Week] [Near Me] [Online]         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 2. Stats Bar - Inline Design

**Changes:**
- Horizontal bar instead of floating stats
- Integrated into hero section bottom
- Smaller, more subtle presentation

### 3. Featured Events - Bento Grid

**Changes:**
- Replace carousel with bento-style grid
- One large featured card + smaller supporting cards
- More visual impact with larger images

```
┌──────────────────────┬──────────┬──────────┐
│                      │          │          │
│    Featured Event    │  Event 2 │  Event 3 │
│    (Large Card)      │          │          │
│                      ├──────────┼──────────┤
│                      │  Event 4 │  Event 5 │
└──────────────────────┴──────────┴──────────┘
```

### 4. Quick Access Section - New

**New addition:**
- Icon-based quick access to popular categories
- Visual category cards with icons

```
┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐
│  📖    │ │  🕌    │ │  💻    │ │  👨‍🏫   │
│ Tafsir │ │ Masjid │ │ Online │ │ Ustaz  │
└────────┘ └────────┘ └────────┘ └────────┘
```

### 5. Upcoming Events - Timeline Style

**Changes:**
- Vertical timeline layout
- Grouped by date
- Cleaner card design

### 6. Browse Section - Map Integration

**Changes:**
- Visual state selector with map preview
- Topic pills in a flowing layout

### 7. CTA Section - Integrated

**Changes:**
- Lighter design
- Integrated into page flow
- Less dramatic but more inviting

---

## Technical Implementation

### Files to Create

1. **Route**
   - `routes/web.php` - Add `/glm` route

2. **Livewire Component**
   - `app/Livewire/Pages/Home/GlmHome.php` - Main component

3. **Blade Templates**
   - `resources/views/livewire/pages/home/glm-home.blade.php` - Main template
   - `resources/views/components/glm/⚡stats.blade.php` - Stats component
   - `resources/views/components/glm/⚡featured.blade.php` - Featured events
   - `resources/views/components/glm/⚡quick-access.blade.php` - Quick access
   - `resources/views/components/glm/⚡timeline.blade.php` - Timeline events
   - `resources/views/components/glm/⚡browse.blade.php` - Browse section
   - `resources/views/components/glm/⚡cta.blade.php` - CTA section

### Reusable Data

The GLM components will reuse the same data queries as the original homepage components to ensure consistency in the data displayed.

---

## Visual Comparison

| Aspect | Original | GLM Alternative |
|--------|----------|-----------------|
| Theme | Dark hero, light body | Light throughout |
| Hero Height | 85vh | 60vh |
| Search | Box with border effects | Pill-shaped, minimal |
| Stats | Floating in hero | Inline bar |
| Featured | Carousel | Bento grid |
| Events | 3-column grid | Timeline |
| Browse | 2-column grid | Map + pills |
| CTA | Dark section | Light integrated |

---

## Implementation Steps

1. **Create Route** - Add `/glm` route pointing to new Livewire component
2. **Create Main Component** - `GlmHome.php` with title attribute
3. **Create Main Template** - `glm-home.blade.php` with hero and structure
4. **Create Stats Component** - Inline stats bar
5. **Create Featured Component** - Bento grid layout
6. **Create Quick Access Component** - Category icons
7. **Create Timeline Component** - Upcoming events timeline
8. **Create Browse Component** - Map and topics
9. **Create CTA Component** - Submit event section
10. **Test and Compare** - Verify both routes work correctly

---

## Design Mockup - Hero Section

```html
<!-- GLM Hero - Light Theme -->
<section class="relative min-h-[60vh] flex items-center bg-gradient-to-b from-emerald-50/50 to-white">
    <div class="container mx-auto px-6 lg:px-12 py-20">
        <div class="max-w-3xl mx-auto text-center">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-slate-900 mb-4">
                Find Islamic Knowledge Events
            </h1>
            <p class="text-lg text-slate-600 mb-8">
                Discover majlis ilmu near you or online
            </p>
            
            <!-- Search Pill -->
            <div class="relative max-w-2xl mx-auto">
                <input type="text" 
                    class="w-full h-14 pl-14 pr-6 rounded-full border border-slate-200 
                           bg-white shadow-lg shadow-slate-200/50 text-slate-900 
                           placeholder-slate-400 focus:outline-none focus:ring-2 
                           focus:ring-emerald-500/20 focus:border-emerald-500"
                    placeholder="Search topics, speakers, locations...">
                <button class="absolute right-2 top-1/2 -translate-y-1/2 h-10 px-6 
                               bg-emerald-600 text-white rounded-full font-medium 
                               hover:bg-emerald-700 transition-colors">
                    Search
                </button>
            </div>
            
            <!-- Quick Filters -->
            <div class="flex flex-wrap justify-center gap-2 mt-6">
                <a href="#" class="px-4 py-2 rounded-full bg-white border border-slate-200 
                                   text-slate-600 text-sm hover:border-emerald-500 
                                   hover:text-emerald-600 transition-colors">
                    Tonight
                </a>
                <!-- More filters... -->
            </div>
        </div>
    </div>
</section>
```

---

## Next Steps

After approval of this plan:
1. Switch to Code mode to implement the route and components
2. Create each component following the design specifications
3. Test the `/glm` route alongside the original homepage
4. Compare and iterate based on feedback

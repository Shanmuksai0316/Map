# Room Allocation & Management Guide for Campus Manager

## 📋 Overview

This guide explains how Campus Managers can:
1. **Allocate rooms to students**
2. **Edit room numbers** (even after tenant activation)

---

## 🏠 Allocating Rooms to Students

### Method 1: From Students List (Recommended)

1. **Navigate to Students**
   - Go to: `https://[tenant-subdomain].mapservices.in/campus-manager/students`
   - Example: `https://ppcu.mapservices.in/campus-manager/students`

2. **Find the Student**
   - Search for the student by name or student ID
   - You'll see their current allocation status in the "Status" column (Assigned/Unassigned)

3. **Allocate Room**
   - Click on the student record (or use the "Allocate Room" action button if visible)
   - In the student view/edit page, you'll see an **"Allocate Room"** action button
   - Click **"Allocate Room"** button

4. **Select Room and Bed**
   - **Hostel**: Shows the default hostel (usually your primary hostel)
   - **Room**: Select from dropdown showing available rooms
     - Format: `Room Number (Floor: X, Block: Y)`
     - Example: `A-1101 (Floor: 11, Block: A)`
   - **Bed**: Select from available beds (A, B, C, or D)
     - Only available beds are shown
   - **Allocation Start Date**: Defaults to today (can be changed)
   - **Notes**: Optional notes about the allocation

5. **Confirm Allocation**
   - Click "Allocate Room" to confirm
   - The student will be assigned to the selected bed
   - Previous allocations (if any) will be automatically deactivated

### Method 2: From Room Visualization Page

1. **Go to Room Visualization**
   - Navigate to: `https://[tenant-subdomain].mapservices.in/campus-manager/room-visualization`
   - This shows a visual overview of all rooms, floors, and bed occupancy

2. **View Room Details**
   - Click on any room to see its details
   - You'll see available beds and occupied beds
   - Navigate to Student Resource to allocate students

---

## ✏️ Editing Room Numbers

### Important Notes

- ✅ **Room numbers are editable** by Campus Manager even after tenant activation
- ❌ **Structural fields are locked** after activation:
  - Block Code (A, B, C, etc.)
  - Floor Code (7, 8, 9, 10, 11, etc.)
  - Bed Capacity (cannot be changed)
  - Room Type (cannot be changed)

### Steps to Edit Room Number

1. **Navigate to Rooms**
   - Go to: `https://[tenant-subdomain].mapservices.in/campus-manager/rooms`
   - Example: `https://ppcu.mapservices.in/campus-manager/rooms`

2. **Find the Room**
   - Search for the room by room number
   - Filter by hostel, floor, or type if needed

3. **Edit Room Number**
   - Click the **Edit** action button (pencil icon) on the room record
   - In the edit form, you can modify the **Room Number** field
   - Room number must be unique within the same hostel

4. **Save Changes**
   - Click "Save" to update the room number
   - The change will be reflected immediately across the system

### Room Number Rules

- Must be unique within the same hostel
- Maximum 16 characters
- Can include letters, numbers, and hyphens (e.g., `A-1101`, `B-902`, `C-701`)
- Changing room number does NOT affect existing student allocations

---

## 📊 Viewing Allocations

### Student Allocation Status

- **Students List**: Shows "Assigned" or "Unassigned" status
- **Room Column**: Shows allocated room number for assigned students
- **Filter**: Filter students by "Assigned" or "Unassigned" status

### Room Occupancy

- **Rooms List**: Shows occupancy as "2/4" (occupied/total beds)
- **Room Visualization**: Visual overview with color-coded occupancy
- **Room Details**: Click any room to see which students are allocated to which beds

---

## 🔍 Quick Tips

1. **Search Students**: Use search bar to find students by name, ID, or room number
2. **Bulk View**: Use "Assigned Rooms" or "Unassigned Rooms" resources for filtered views
3. **Allocation History**: Student allocations are tracked with effective dates
4. **Room Changes**: Students can request room changes (separate workflow)

---

## 🆘 Troubleshooting

### "No available beds" error
- Check if room has available beds
- Use Room Visualization to see bed status
- Ensure beds are not blocked for maintenance

### "Room number already exists" error
- Room number must be unique within hostel
- Check existing rooms in the same hostel
- Use a different room number

### Cannot edit room number
- Ensure you're logged in as Campus Manager
- Check that you're editing (not viewing) the room
- If issue persists, contact Super Admin

---

## 📍 Access Links

- **Students**: `https://ppcu.mapservices.in/campus-manager/students`
- **Rooms**: `https://ppcu.mapservices.in/campus-manager/rooms`
- **Room Visualization**: `https://ppcu.mapservices.in/campus-manager/room-visualization`
- **Assigned Rooms**: `https://ppcu.mapservices.in/campus-manager/assigned-rooms`
- **Unassigned Rooms**: `https://ppcu.mapservices.in/campus-manager/unassigned-rooms`

---

**Last Updated**: January 2026
**Applies to**: Campus Manager role only


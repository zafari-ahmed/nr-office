import {
    createIcons, LayoutDashboard, FolderKanban, FileText, Receipt, Wallet, Package, Users, RefreshCw, BarChart3,
    Settings, Search, Plus, Bell, Moon, Sun, ChevronLeft, ChevronRight, ChevronDown, Menu, X, LogOut, Building2,
    MapPin, PhoneCall, CalendarClock, CalendarDays, Clock, Check, CheckCircle2, AlertTriangle, AlertCircle, ArrowUpRight,
    ArrowDownRight, Paperclip, Mail, Upload, Download, Trash2, Pencil, Printer, Send, Copy, ArrowRightLeft, MoreHorizontal,
    Filter, LayoutGrid, List, Columns3, StickyNote, Pin, History, UserPlus, User, Phone, IdCard, Banknote, Briefcase,
    Hash, Percent, Inbox, FileUp, Eye, CircleDollarSign, TrendingUp, Plane, Home, Tag, Boxes, Laptop, ShieldCheck, Logs,
    MessageSquare, AlarmClock, Undo2, ExternalLink, Sparkles, HardHat,
} from 'lucide';

const icons = {
    LayoutDashboard, FolderKanban, FileText, Receipt, Wallet, Package, Users, RefreshCw, BarChart3, Settings, Search,
    Plus, Bell, Moon, Sun, ChevronLeft, ChevronRight, ChevronDown, Menu, X, LogOut, Building2, MapPin, PhoneCall,
    CalendarClock, CalendarDays, Clock, Check, CheckCircle2, AlertTriangle, AlertCircle, ArrowUpRight, ArrowDownRight,
    Paperclip, Mail, Upload, Download, Trash2, Pencil, Printer, Send, Copy, ArrowRightLeft, MoreHorizontal, Filter,
    LayoutGrid, List, Columns3, StickyNote, Pin, History, UserPlus, User, Phone, IdCard, Banknote, Briefcase, Hash,
    Percent, Inbox, FileUp, Eye, CircleDollarSign, TrendingUp, Plane, Home, Tag, Boxes, Laptop, ShieldCheck, Logs,
    MessageSquare, AlarmClock, Undo2, ExternalLink, Sparkles, HardHat,
};

export function renderIcons() {
    createIcons({ icons, attrs: { 'stroke-width': 1.75 } });
}

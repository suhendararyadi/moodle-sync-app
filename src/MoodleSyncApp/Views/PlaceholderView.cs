using Avalonia.Controls;
using Avalonia.Layout;

namespace MoodleSyncApp.Views;

/// <summary>
/// View placeholder sementara untuk fitur yang belum diimplementasi
/// </summary>
public class PlaceholderView : UserControl
{
    public PlaceholderView(string title, string description)
    {
        Content = new StackPanel
        {
            HorizontalAlignment = HorizontalAlignment.Center,
            VerticalAlignment   = VerticalAlignment.Center,
            Spacing = 12,
            Children =
            {
                new TextBlock
                {
                    Text     = title,
                    FontSize = 22,
                    FontWeight = Avalonia.Media.FontWeight.Bold,
                    HorizontalAlignment = HorizontalAlignment.Center
                },
                new TextBlock
                {
                    Text      = description,
                    FontSize  = 13,
                    Foreground = new Avalonia.Media.SolidColorBrush(
                        new Avalonia.Media.Color(255, 102, 102, 102)),
                    HorizontalAlignment = HorizontalAlignment.Center,
                    TextWrapping = Avalonia.Media.TextWrapping.Wrap,
                    MaxWidth = 400
                },
                new TextBlock
                {
                    Text      = "🚧 Coming Soon",
                    FontSize  = 13,
                    Foreground = new Avalonia.Media.SolidColorBrush(
                        new Avalonia.Media.Color(255, 156, 39, 176)),
                    HorizontalAlignment = HorizontalAlignment.Center
                }
            }
        };
    }
}
